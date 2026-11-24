<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Conversation\Command;

use App\Application\Conversation\Command\SendMessageCommand;
use App\Application\Conversation\Command\SendMessageCommandHandler;
use App\Domain\Conversation\Conversation;
use App\Domain\Conversation\Exception\ConversationNotFoundException;
use App\Domain\Conversation\MessageRole;
use App\Domain\Conversation\Repository\ConversationRepositoryInterface;
use App\Domain\Conversation\Repository\MessageRepositoryInterface;
use App\Domain\Conversation\Repository\TurnRepositoryInterface;
use App\Domain\Conversation\TurnStatus;
use App\Domain\Port\DateUtilsInterface;
use App\Domain\Port\IdFactoryInterface;
use PHPUnit\Framework\TestCase;

final class SendMessageCommandHandlerTest extends TestCase
{
    public function testRecordsTheMessageAndOpensATurnInProgress(): void
    {
        $conversation = new Conversation('c1', 'owner', new \DateTimeImmutable('2026-11-24 09:00:00'));

        $messageRepository = $this->createMock(MessageRepositoryInterface::class);
        $messageRepository->expects(self::once())->method('add')->willReturnArgument(0);
        $turnRepository = $this->createMock(TurnRepositoryInterface::class);
        $turnRepository->expects(self::once())->method('add')->willReturnArgument(0);

        $turn = $this->handler($conversation, $messageRepository, $turnRepository)(
            new SendMessageCommand('c1', 'owner', '  Mon différentiel saute  '),
        );

        self::assertSame('turn-uuid', $turn->getUuid());
        self::assertSame(TurnStatus::InProgress, $turn->getStatus());
        self::assertSame(MessageRole::User, $turn->getUserMessage()->getRole());
        self::assertSame('Mon différentiel saute', $turn->getUserMessage()->getBlocks()[0]->content['text']);
        self::assertSame('Mon différentiel saute', $conversation->getTitle());
        self::assertEquals(new \DateTimeImmutable('2026-11-24 10:00:00'), $conversation->getUpdatedAt());
    }

    public function testTheConversationOfAnotherUserIsUnknown(): void
    {
        $conversation = new Conversation('c1', 'owner', new \DateTimeImmutable());

        $messageRepository = $this->createMock(MessageRepositoryInterface::class);
        $messageRepository->expects(self::never())->method('add');
        $turnRepository = $this->createMock(TurnRepositoryInterface::class);
        $turnRepository->expects(self::never())->method('add');

        // CV1 : même réponse que pour une conversation qui n'existe pas
        $this->expectException(ConversationNotFoundException::class);

        $this->handler($conversation, $messageRepository, $turnRepository)(new SendMessageCommand('c1', 'intrus', 'Bonjour'));
    }

    private function handler(
        Conversation $conversation,
        MessageRepositoryInterface $messageRepository,
        TurnRepositoryInterface $turnRepository,
    ): SendMessageCommandHandler {
        $conversationRepository = $this->createStub(ConversationRepositoryInterface::class);
        $conversationRepository->method('findOneByUuid')->willReturnCallback(
            static fn (string $uuid): ?Conversation => $uuid === $conversation->getUuid() ? $conversation : null,
        );

        $idFactory = $this->createStub(IdFactoryInterface::class);
        $idFactory->method('make')->willReturn('message-uuid', 'turn-uuid');

        $dateUtils = $this->createStub(DateUtilsInterface::class);
        $dateUtils->method('getNow')->willReturn(new \DateTimeImmutable('2026-11-24 10:00:00'));

        return new SendMessageCommandHandler($conversationRepository, $messageRepository, $turnRepository, $idFactory, $dateUtils);
    }
}
