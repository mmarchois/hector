<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Conversation\Service;

use App\Application\Conversation\Port\ChatMessage;
use App\Application\Conversation\Port\ChatRole;
use App\Application\Conversation\Port\Participant;
use App\Application\Conversation\Port\ParticipantInterface;
use App\Application\Conversation\Port\Prompt;
use App\Application\Conversation\Port\PromptCatalogInterface;
use App\Application\Conversation\Service\TurnContextBuilder;
use App\Domain\Conversation\Block;
use App\Domain\Conversation\Conversation;
use App\Domain\Conversation\Message;
use App\Domain\Conversation\MessageRole;
use App\Domain\Conversation\Repository\MessageRepositoryInterface;
use App\Domain\Conversation\Turn;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TurnContextBuilderTest extends TestCase
{
    private Conversation $conversation;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-24 10:00:00');
        $this->conversation = new Conversation('c1', 'owner', $this->now);
    }

    /** @return iterable<string, array{Participant, string}> */
    public static function audiences(): iterable
    {
        yield 'professionnel' => [new Participant(guest: false, professional: true), 'PRO'];
        yield 'particulier' => [new Participant(guest: false, professional: false), 'PARTICULIER'];
        // ID3 : un invité a la qualification la plus restrictive
        yield 'invité' => [new Participant(guest: true, professional: true), 'PARTICULIER'];
    }

    #[DataProvider('audiences')]
    public function testTheInstructionsDescribeTheAudience(Participant $participant, string $expected): void
    {
        $prepared = $this->builder($participant, [])->build($this->turn('Bonjour'));

        self::assertSame("Consignes.\n\n$expected", $prepared->request->system);
        self::assertSame(substr(hash('sha256', $prepared->request->system), 0, 12), $prepared->promptVersion);
    }

    public function testTheHistoryAlternatesAndStartsWithTheUser(): void
    {
        $history = [
            // Fenêtre commencée sur une réponse : écartée
            $this->message('Réponse ancienne', MessageRole::Assistant),
            $this->message('Question 1', MessageRole::User),
            $this->message('Réponse 1', MessageRole::Assistant),
            // Tour échoué : message resté sans réponse, fusionné avec le suivant
            $this->message('Question 2', MessageRole::User),
        ];

        $prepared = $this->builder(new Participant(false, true), $history)->build($this->turn('Question 2, bis'));

        self::assertEquals([
            new ChatMessage(ChatRole::User, 'Question 1'),
            new ChatMessage(ChatRole::Assistant, 'Réponse 1'),
            new ChatMessage(ChatRole::User, "Question 2\n\nQuestion 2, bis"),
        ], $prepared->request->messages);
    }

    public function testALongHistoryIsTrimmedFromItsOldestMessages(): void
    {
        $long = str_repeat('é', 15_000);
        $history = [
            $this->message("Ancienne $long", MessageRole::User),
            $this->message("Réponse ancienne $long", MessageRole::Assistant),
            $this->message("Récente $long", MessageRole::User),
            $this->message("Réponse récente $long", MessageRole::Assistant),
        ];

        $messages = $this->builder(new Participant(false, true), $history)->build($this->turn('Nouvelle question'))->request->messages;

        // Au-delà de la borne, les plus anciens partent, et la fenêtre commence par l'utilisateur
        self::assertCount(3, $messages);
        self::assertStringStartsWith('Récente', $messages[0]->text);
        self::assertSame('Nouvelle question', $messages[2]->text);
        $total = array_sum(array_map(static fn (ChatMessage $message): int => mb_strlen($message->text), $messages));
        self::assertLessThanOrEqual(TurnContextBuilder::HISTORY_MAX_CHARACTERS, $total);
    }

    public function testTheMessageOfTheTurnIsAlwaysSent(): void
    {
        $history = [$this->message(str_repeat('a', 45_000), MessageRole::Assistant)];

        $messages = $this->builder(new Participant(false, true), $history)->build($this->turn('Question'))->request->messages;

        self::assertEquals([new ChatMessage(ChatRole::User, 'Question')], $messages);
    }

    /**
     * @param list<Message> $history
     */
    private function builder(Participant $participant, array $history): TurnContextBuilder
    {
        $prompts = $this->createStub(PromptCatalogInterface::class);
        $prompts->method('get')->willReturnCallback(static fn (string $name): Prompt => new Prompt($name, 'v', match ($name) {
            'conversation-system' => "Consignes.\n\n{{audience}}",
            'audience-professional' => 'PRO',
            'audience-individual' => 'PARTICULIER',
            default => throw new \LogicException($name),
        }));
        $participants = $this->createStub(ParticipantInterface::class);
        $participants->method('profileOf')->willReturn($participant);
        $messages = $this->createStub(MessageRepositoryInterface::class);
        $messages->method('findPage')->willReturn($history);

        return new TurnContextBuilder($prompts, $participants, $messages);
    }

    private function turn(string $text): Turn
    {
        return new Turn('t1', $this->conversation, $this->message($text, MessageRole::User), $this->now);
    }

    private function message(string $text, MessageRole $role): Message
    {
        return new Message(uniqid('m', true), $this->conversation, $role, [Block::text($text)], $this->now);
    }
}
