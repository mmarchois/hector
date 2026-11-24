<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Conversation\Query;

use App\Application\Conversation\Query\GetMessagesQuery;
use App\Application\Conversation\Query\GetMessagesQueryHandler;
use App\Application\Conversation\Query\GetTurnQuery;
use App\Application\Conversation\Query\GetTurnQueryHandler;
use App\Domain\Conversation\Block;
use App\Domain\Conversation\Conversation;
use App\Domain\Conversation\Exception\ConversationNotFoundException;
use App\Domain\Conversation\Exception\TurnNotFoundException;
use App\Domain\Conversation\Message;
use App\Domain\Conversation\MessageRole;
use App\Domain\Conversation\Repository\ConversationRepositoryInterface;
use App\Domain\Conversation\Repository\MessageRepositoryInterface;
use App\Domain\Conversation\Repository\TurnRepositoryInterface;
use App\Domain\Conversation\Turn;
use PHPUnit\Framework\TestCase;

/** CV1 : seul le propriétaire lit une conversation et ses tours. */
final class OwnershipTest extends TestCase
{
    private Conversation $conversation;

    protected function setUp(): void
    {
        $this->conversation = new Conversation('c1', 'owner', new \DateTimeImmutable());
    }

    public function testTheOwnerReadsTheLatestMessages(): void
    {
        $messages = [$this->message('m1'), $this->message('m2')];
        $messageRepository = $this->createMock(MessageRepositoryInterface::class);
        $messageRepository->expects(self::once())->method('findPage')->with($this->conversation, null, 30)->willReturn($messages);
        $messageRepository->expects(self::once())->method('existsBefore')->with($this->conversation, 'm1')->willReturn(true);

        $page = $this->messagesHandler($messageRepository)(new GetMessagesQuery('c1', 'owner'));

        self::assertSame($messages, $page->items);
        self::assertTrue($page->hasMore);
    }

    public function testAnotherUserCannotReadTheMessages(): void
    {
        $messageRepository = $this->createMock(MessageRepositoryInterface::class);
        $messageRepository->expects(self::never())->method('findPage');

        $this->expectException(ConversationNotFoundException::class);

        $this->messagesHandler($messageRepository)(new GetMessagesQuery('c1', 'intrus'));
    }

    public function testAnotherUserCannotFollowTheTurn(): void
    {
        $turn = new Turn('t1', $this->conversation, $this->message('m1'), new \DateTimeImmutable());
        $turnRepository = $this->createStub(TurnRepositoryInterface::class);
        $turnRepository->method('findOneByUuid')->willReturn($turn);
        $handler = new GetTurnQueryHandler($turnRepository);

        self::assertSame($turn, $handler(new GetTurnQuery('t1', 'owner')));

        $this->expectException(TurnNotFoundException::class);
        $handler(new GetTurnQuery('t1', 'intrus'));
    }

    private function messagesHandler(MessageRepositoryInterface $messageRepository): GetMessagesQueryHandler
    {
        $conversationRepository = $this->createStub(ConversationRepositoryInterface::class);
        $conversationRepository->method('findOneByUuid')->willReturn($this->conversation);

        return new GetMessagesQueryHandler($conversationRepository, $messageRepository);
    }

    private function message(string $uuid): Message
    {
        return new Message($uuid, $this->conversation, MessageRole::User, [Block::text('Bonjour')], new \DateTimeImmutable());
    }
}
