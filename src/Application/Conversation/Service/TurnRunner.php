<?php

declare(strict_types=1);

namespace App\Application\Conversation\Service;

use App\Application\CommandBusInterface;
use App\Application\Conversation\Command\AnswerTurnCommand;
use App\Application\Conversation\Command\FailTurnCommand;
use App\Application\Conversation\Command\SendMessageCommand;
use App\Application\Conversation\Port\ChatModelException;
use App\Application\Conversation\Port\ChatModelInterface;
use App\Application\Conversation\Port\ChatModelUnavailableException;
use App\Application\Conversation\Port\ChatResponse;
use App\Application\Conversation\Port\ChatStopReason;
use App\Domain\Conversation\Block;
use App\Domain\Conversation\Turn;
use App\Domain\Conversation\TurnFailure;
use App\Domain\Conversation\TurnTrace;

/**
 * Déroulé d'un tour (docs/ARCHITECTURE.md, § 5) : deux transactions courtes et, entre les
 * deux, hors de toute transaction, l'appel au modèle de langage.
 */
final readonly class TurnRunner
{
    /** Réponse fixe quand le fournisseur refuse de répondre. */
    public const string REFUSAL_REPLY = 'Je ne peux pas répondre à cette demande. Pour toute intervention sur une installation électrique, faites appel à un électricien qualifié.';

    /** Réponse fixe quand le modèle ne renvoie aucun texte. */
    public const string EMPTY_REPLY = 'Je n\'ai pas pu formuler de réponse. Pouvez-vous reformuler votre question ?';

    public function __construct(
        private CommandBusInterface $commandBus,
        private TurnContextBuilder $contextBuilder,
        private ChatModelInterface $chatModel,
    ) {
    }

    public function send(string $conversationUuid, string $ownerUuid, string $text): Turn
    {
        // 1. Message de l'utilisateur et tour « en cours » (transaction courte)
        /** @var Turn $turn */
        $turn = $this->commandBus->handle(new SendMessageCommand($conversationUuid, $ownerUuid, $text));

        // 2. Contexte et appel au modèle, hors transaction
        $prepared = $this->contextBuilder->build($turn);
        $startedAt = hrtime(true);

        try {
            $response = $this->chatModel->complete($prepared->request);
        } catch (ChatModelException $e) {
            return $this->fail($turn, $e, $prepared, $startedAt);
        }

        // 3. Réponse et trace (transaction courte)
        $trace = $this->trace($response, $prepared, $startedAt);
        $answer = trim($response->text);

        return match (true) {
            $response->stopReason === ChatStopReason::Refusal => $this->answer($turn, self::REFUSAL_REPLY, $trace, degraded: true),
            $answer === '' => $this->answer($turn, self::EMPTY_REPLY, $trace, degraded: true),
            default => $this->answer($turn, $answer, $trace),
        };
    }

    private function answer(Turn $turn, string $text, TurnTrace $trace, bool $degraded = false): Turn
    {
        /** @var Turn $answered */
        $answered = $this->commandBus->handle(new AnswerTurnCommand($turn->getUuid(), [Block::text($text)], $degraded, $trace));

        return $answered;
    }

    private function fail(Turn $turn, ChatModelException $e, PreparedTurn $prepared, int $startedAt): Turn
    {
        $trace = new TurnTrace(
            promptVersion: $prepared->promptVersion,
            durationMs: $this->elapsedMs($startedAt),
            error: mb_substr($e->getMessage(), 0, 255),
        );
        $failure = $e instanceof ChatModelUnavailableException ? TurnFailure::Unavailable : TurnFailure::Error;

        /** @var Turn $failed */
        $failed = $this->commandBus->handle(new FailTurnCommand($turn->getUuid(), $failure, $trace));

        return $failed;
    }

    private function trace(ChatResponse $response, PreparedTurn $prepared, int $startedAt): TurnTrace
    {
        return new TurnTrace(
            model: $response->model,
            promptVersion: $prepared->promptVersion,
            stopReason: $response->stopReason->value,
            inputTokens: $response->usage->inputTokens,
            outputTokens: $response->usage->outputTokens,
            cacheReadInputTokens: $response->usage->cacheReadInputTokens,
            cacheCreationInputTokens: $response->usage->cacheCreationInputTokens,
            durationMs: $this->elapsedMs($startedAt),
        );
    }

    private function elapsedMs(int $startedAt): int
    {
        return intdiv(hrtime(true) - $startedAt, 1_000_000);
    }
}
