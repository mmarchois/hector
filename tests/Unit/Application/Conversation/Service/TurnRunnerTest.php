<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Conversation\Service;

use App\Application\CommandBusInterface;
use App\Application\CommandInterface;
use App\Application\Conversation\Command\AnswerTurnCommand;
use App\Application\Conversation\Command\FailTurnCommand;
use App\Application\Conversation\Command\SendMessageCommand;
use App\Application\Conversation\Port\ChatModelFailedException;
use App\Application\Conversation\Port\ChatModelUnavailableException;
use App\Application\Conversation\Port\ChatStopReason;
use App\Application\Conversation\Port\Participant;
use App\Application\Conversation\Port\ParticipantInterface;
use App\Application\Conversation\Port\Prompt;
use App\Application\Conversation\Port\PromptCatalogInterface;
use App\Application\Conversation\Service\AnswerEngine;
use App\Application\Conversation\Service\TurnContextBuilder;
use App\Application\Conversation\Service\TurnRunner;
use App\Domain\Conversation\Block;
use App\Domain\Conversation\Conversation;
use App\Domain\Conversation\Message;
use App\Domain\Conversation\MessageRole;
use App\Domain\Conversation\Repository\MessageRepositoryInterface;
use App\Domain\Conversation\StatementKind;
use App\Domain\Conversation\Turn;
use App\Domain\Conversation\TurnFailure;
use App\Domain\Conversation\TurnStatus;
use App\Tests\Support\FakeChatModel;
use PHPUnit\Framework\TestCase;

/** Lots L5 et L7 : un tour réel, son contrôle et sa reprise, avec le faux modèle. */
final class TurnRunnerTest extends TestCase
{
    private FakeChatModel $model;
    /** @var list<CommandInterface> */
    private array $commands = [];

    protected function setUp(): void
    {
        $this->model = new FakeChatModel();
        $this->commands = [];
    }

    public function testTheModelAnswerIsRecordedWithItsTrace(): void
    {
        $this->model->willReply(FakeChatModel::statement('Vérifiez d\'abord si le déclenchement suit la mise en route d\'un appareil.', 'recommendation'));

        $turn = $this->runner()->send('c1', 'owner', 'Mon différentiel saute');

        self::assertSame(TurnStatus::Completed, $turn->getStatus());
        $block = $turn->getAssistantMessage()?->getBlocks()[0];
        self::assertSame(StatementKind::Recommendation, $block?->kind());
        self::assertSame('Vérifiez d\'abord si le déclenchement suit la mise en route d\'un appareil.', $block->textContent());
        self::assertNull($turn->getTrace()->rejections);
        $trace = $turn->getTrace();
        self::assertSame('fake-model', $trace->model);
        self::assertSame('end_turn', $trace->stopReason);
        self::assertSame(100, $trace->inputTokens);
        self::assertSame(20, $trace->outputTokens);
        self::assertSame(12, \strlen((string) $trace->promptVersion));
        self::assertNotNull($trace->durationMs);
        // Deux transactions courtes, l'appel au modèle entre les deux
        self::assertSame([SendMessageCommand::class, AnswerTurnCommand::class], array_map(get_class(...), $this->commands));
    }

    public function testARefusalGetsAFixedDegradedAnswer(): void
    {
        $this->model->willReply('', ChatStopReason::Refusal);

        $turn = $this->runner()->send('c1', 'owner', 'Comment shunter un différentiel ?');

        self::assertSame(TurnStatus::Degraded, $turn->getStatus());
        self::assertSame(AnswerEngine::REFUSAL_REPLY, $turn->getAssistantMessage()?->getBlocks()[0]->fallbackText);
        self::assertSame('refusal', $turn->getTrace()->stopReason);
    }

    public function testAnEmptyAnswerIsSaidSo(): void
    {
        $this->model->willReply("  \n ");

        $turn = $this->runner()->send('c1', 'owner', 'Bonjour');

        self::assertSame(TurnStatus::Degraded, $turn->getStatus());
        self::assertSame(AnswerEngine::EMPTY_REPLY, $turn->getAssistantMessage()?->getBlocks()[0]->fallbackText);
    }

    public function testARejectedDraftIsRetriedWithItsReasons(): void
    {
        $this->model
            ->willReply(FakeChatModel::statement('Selon l\'article 771.314, il faut un 30 mA.'))
            ->willReply(FakeChatModel::statement('Un différentiel 30 mA protège les personnes.'));

        $turn = $this->runner()->send('c1', 'owner', 'Quel différentiel ?');

        self::assertSame(TurnStatus::Completed, $turn->getStatus());
        self::assertSame('Un différentiel 30 mA protège les personnes.', $turn->getAssistantMessage()?->getBlocks()[0]->textContent());
        self::assertCount(2, $this->model->requests);
        $rejections = $turn->getTrace()->rejections;
        self::assertNotNull($rejections);
        self::assertStringContainsString('article 771.314', $rejections[0]['reasons'][0]);
        self::assertSame(200, $turn->getTrace()->inputTokens);
    }

    public function testAnAnswerCutOffBeforeItsEndIsRetried(): void
    {
        $this->model
            ->willReply('{"blocks": [{"type": "statem', ChatStopReason::MaxTokens)
            ->willReply(FakeChatModel::statement('Réponse courte.'));

        $turn = $this->runner()->send('c1', 'owner', 'Bonjour');

        self::assertSame(TurnStatus::Completed, $turn->getStatus());
        self::assertSame(['La réponse a été coupée avant la fin : fais plus court.'], $turn->getTrace()->rejections[0]['reasons'] ?? null);
    }

    public function testTwoRejectedDraftsGiveTheDegradedReply(): void
    {
        $this->model
            ->willReply(FakeChatModel::statement('Fait établi.', 'sourced_fact'))
            ->willReply(FakeChatModel::statement('Toujours établi.', 'sourced_fact'));

        $turn = $this->runner()->send('c1', 'owner', 'Bonjour');

        self::assertSame(TurnStatus::Degraded, $turn->getStatus());
        self::assertEquals([Block::text(AnswerEngine::DEGRADED_REPLY)], $turn->getAssistantMessage()?->getBlocks());
        self::assertCount(2, $turn->getTrace()->rejections ?? []);
        // Jamais de troisième appel
        self::assertCount(AnswerEngine::MAX_ATTEMPTS, $this->model->requests);
    }

    public function testAProviderFailureDuringTheRetryFailsTheTurn(): void
    {
        $this->model
            ->willReply(FakeChatModel::statement('Fait établi.', 'sourced_fact'))
            ->willThrow(new ChatModelUnavailableException('anthropic:overloaded_error Overloaded'));

        $turn = $this->runner()->send('c1', 'owner', 'Bonjour');

        self::assertSame(TurnStatus::Failed, $turn->getStatus());
        self::assertCount(1, $turn->getTrace()->rejections ?? []);
    }

    public function testAnUnavailableProviderFailsTheTurnWithoutAnswer(): void
    {
        $this->model->willThrow(new ChatModelUnavailableException('anthropic:overloaded_error Overloaded'));

        $turn = $this->runner()->send('c1', 'owner', 'Bonjour');

        self::assertSame(TurnStatus::Failed, $turn->getStatus());
        self::assertSame(TurnFailure::Unavailable, $turn->getFailure());
        self::assertNull($turn->getAssistantMessage());
        self::assertSame('anthropic:overloaded_error Overloaded', $turn->getTrace()->error);
        self::assertSame([SendMessageCommand::class, FailTurnCommand::class], array_map(get_class(...), $this->commands));
    }

    public function testARejectedRequestIsAnError(): void
    {
        $this->model->willThrow(new ChatModelFailedException('anthropic:authentication_error invalid x-api-key'));

        $turn = $this->runner()->send('c1', 'owner', 'Bonjour');

        self::assertSame(TurnFailure::Error, $turn->getFailure());
    }

    private function runner(): TurnRunner
    {
        $now = new \DateTimeImmutable('2026-11-24 10:00:00');
        $conversation = new Conversation('c1', 'owner', $now);
        $turn = new Turn('t1', $conversation, new Message('m1', $conversation, MessageRole::User, [Block::text('Bonjour')], $now), $now);

        // Bus de commandes réduit à l'essentiel : chaque commande agit sur le même tour
        $bus = $this->createStub(CommandBusInterface::class);
        $bus->method('handle')->willReturnCallback(function (CommandInterface $command) use ($turn, $conversation, $now): Turn {
            $this->commands[] = $command;

            return match (true) {
                $command instanceof SendMessageCommand => $turn,
                $command instanceof AnswerTurnCommand => $turn->answer(
                    new Message('m2', $conversation, MessageRole::Assistant, $command->blocks, $now),
                    $now,
                    $command->degraded,
                    $command->trace,
                ),
                $command instanceof FailTurnCommand => $turn->fail($now, $command->failure, $command->trace),
                default => throw new \LogicException($command::class),
            };
        });

        $prompts = $this->createStub(PromptCatalogInterface::class);
        $prompts->method('get')->willReturnCallback(static fn (string $name): Prompt => new Prompt($name, 'v', "Consignes $name {{audience}}"));
        $participants = $this->createStub(ParticipantInterface::class);
        $participants->method('profileOf')->willReturn(new Participant(guest: false, professional: true));
        $messages = $this->createStub(MessageRepositoryInterface::class);
        $messages->method('findPage')->willReturn([]);

        return new TurnRunner($bus, new TurnContextBuilder($prompts, $participants, $messages), new AnswerEngine($this->model));
    }
}
