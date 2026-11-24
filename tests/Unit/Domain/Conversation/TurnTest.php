<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Conversation;

use App\Domain\Conversation\Block;
use App\Domain\Conversation\Conversation;
use App\Domain\Conversation\Exception\TurnAlreadyFinishedException;
use App\Domain\Conversation\Message;
use App\Domain\Conversation\MessageRole;
use App\Domain\Conversation\Turn;
use App\Domain\Conversation\TurnStatus;
use PHPUnit\Framework\TestCase;

/** Règles CV2, CV3 et CV5 (docs/DOMAIN.md, § 4.1). */
final class TurnTest extends TestCase
{
    private Conversation $conversation;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-24 10:00:00');
        $this->conversation = new Conversation('c1', 'owner', $this->now);
    }

    public function testATurnStartsInProgressThenReceivesOneAnswer(): void
    {
        $turn = new Turn('t1', $this->conversation, $this->message('m1', MessageRole::User), $this->now);
        self::assertSame(TurnStatus::InProgress, $turn->getStatus());
        self::assertNull($turn->getAssistantMessage());

        $answer = $this->message('m2', MessageRole::Assistant);
        $turn->answer($answer, $this->now->modify('+2 seconds'));

        self::assertSame(TurnStatus::Completed, $turn->getStatus());
        self::assertSame($answer, $turn->getAssistantMessage());
        self::assertEquals($this->now->modify('+2 seconds'), $turn->getFinishedAt());
    }

    public function testADegradedAnswerIsSaidSo(): void
    {
        $turn = new Turn('t1', $this->conversation, $this->message('m1', MessageRole::User), $this->now);

        $turn->answer($this->message('m2', MessageRole::Assistant), $this->now, degraded: true);

        self::assertSame(TurnStatus::Degraded, $turn->getStatus());
    }

    public function testAFinishedTurnReceivesNoOtherAnswer(): void
    {
        $turn = new Turn('t1', $this->conversation, $this->message('m1', MessageRole::User), $this->now);
        $turn->answer($this->message('m2', MessageRole::Assistant), $this->now);

        $this->expectException(TurnAlreadyFinishedException::class);

        $turn->answer($this->message('m3', MessageRole::Assistant), $this->now);
    }

    public function testAFailedTurnHasNoAnswerAndCannotBeAnsweredLater(): void
    {
        $turn = new Turn('t1', $this->conversation, $this->message('m1', MessageRole::User), $this->now);
        $turn->fail($this->now);

        self::assertSame(TurnStatus::Failed, $turn->getStatus());
        self::assertNull($turn->getAssistantMessage());
        $this->expectException(TurnAlreadyFinishedException::class);

        $turn->answer($this->message('m2', MessageRole::Assistant), $this->now);
    }

    public function testATurnStartsFromAUserMessageOfTheSameConversation(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Turn('t1', $this->conversation, $this->message('m1', MessageRole::Assistant), $this->now);
    }

    public function testTheAnswerIsAnAssistantMessage(): void
    {
        $turn = new Turn('t1', $this->conversation, $this->message('m1', MessageRole::User), $this->now);

        $this->expectException(\InvalidArgumentException::class);

        $turn->answer($this->message('m2', MessageRole::User), $this->now);
    }

    public function testEveryBlockCarriesAFallbackText(): void
    {
        $message = $this->message('m1', MessageRole::User);

        self::assertSame(
            [['type' => 'text', 'version' => 1, 'text' => 'Bonjour', 'fallbackText' => 'Bonjour']],
            array_map(static fn (Block $block): array => $block->toArray(), $message->getBlocks()),
        );
    }

    public function testAMessageHasAtLeastOneBlock(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Message('m1', $this->conversation, MessageRole::User, [], $this->now);
    }

    public function testTheFirstMessageTitlesTheConversation(): void
    {
        $long = str_repeat('différentiel ', 20);

        $this->conversation->recordUserMessage("  Mon   différentiel\nsaute  ", $this->now);
        $this->conversation->recordUserMessage('Un autre message', $this->now->modify('+1 minute'));

        self::assertSame('Mon différentiel saute', $this->conversation->getTitle());
        self::assertEquals($this->now->modify('+1 minute'), $this->conversation->getUpdatedAt());

        $other = (new Conversation('c2', 'owner', $this->now))->recordUserMessage($long, $this->now);
        self::assertSame(Conversation::TITLE_LENGTH, mb_strlen((string) $other->getTitle()));
        self::assertStringEndsWith('…', (string) $other->getTitle());
    }

    private function message(string $uuid, MessageRole $role): Message
    {
        return new Message($uuid, $this->conversation, $role, [Block::text('Bonjour')], $this->now);
    }
}
