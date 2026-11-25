<?php

declare(strict_types=1);

namespace App\Application\Conversation\Service;

use App\Application\CommandBusInterface;
use App\Application\Conversation\Command\AnswerTurnCommand;
use App\Application\Conversation\Command\FailTurnCommand;
use App\Application\Conversation\Command\SendMessageCommand;
use App\Application\Conversation\Port\ChatMessage;
use App\Application\Conversation\Port\ChatModelException;
use App\Application\Conversation\Port\ChatModelInterface;
use App\Application\Conversation\Port\ChatModelUnavailableException;
use App\Application\Conversation\Port\ChatRequest;
use App\Application\Conversation\Port\ChatResponse;
use App\Application\Conversation\Port\ChatRole;
use App\Application\Conversation\Port\ChatStopReason;
use App\Domain\Conversation\Block;
use App\Domain\Conversation\DraftValidator;
use App\Domain\Conversation\Turn;
use App\Domain\Conversation\TurnFailure;

/**
 * Déroulé d'un tour (docs/ARCHITECTURE.md, § 5) : deux transactions courtes et, entre les
 * deux, hors de toute transaction, l'appel au modèle et le contrôle de son brouillon.
 * Un brouillon rejeté donne lieu à une seule reprise, avec les motifs du rejet ; si la
 * reprise est rejetée à son tour, l'utilisateur reçoit une réponse dégradée qui le dit.
 * Un brouillon rejeté n'est jamais montré à l'utilisateur (docs/SECURITY.md, § 4).
 */
final readonly class TurnRunner
{
    /** Réponse fixe quand le fournisseur refuse de répondre. */
    public const string REFUSAL_REPLY = 'Je ne peux pas répondre à cette demande. Pour toute intervention sur une installation électrique, faites appel à un électricien qualifié.';

    /** Réponse fixe quand le modèle ne renvoie aucun texte. */
    public const string EMPTY_REPLY = 'Je n\'ai pas pu formuler de réponse. Pouvez-vous reformuler votre question ?';

    /** Réponse fixe quand le brouillon et sa reprise sont rejetés par le contrôle. */
    public const string DEGRADED_REPLY = 'Je n\'ai pas pu formuler une réponse assez fiable à cette question. Pouvez-vous la préciser ou la reformuler ? Pour une intervention sur une installation, faites appel à un électricien qualifié.';

    /** Appels au modèle pour un tour : le brouillon et une reprise. */
    public const int MAX_ATTEMPTS = 2;

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

        // 2. Contexte, appels au modèle et contrôle, hors transaction
        $prepared = $this->contextBuilder->build($turn);
        $request = new ChatRequest($prepared->request->system, $prepared->request->messages, AnswerFormat::schema());
        $trace = new TraceRecorder($prepared->promptVersion);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            try {
                $response = $this->chatModel->complete($request);
            } catch (ChatModelException $e) {
                return $this->fail($turn, $e, $trace);
            }
            $trace->record($response);

            if ($response->stopReason === ChatStopReason::Refusal) {
                return $this->answer($turn, [Block::text(self::REFUSAL_REPLY)], $trace, degraded: true);
            }
            if (trim($response->text) === '') {
                return $this->answer($turn, [Block::text(self::EMPTY_REPLY)], $trace, degraded: true);
            }

            [$blocks, $reasons] = $this->check($response);
            // 3. Réponse et trace (transaction courte)
            if ($reasons === []) {
                return $this->answer($turn, $blocks, $trace);
            }
            $trace->reject($attempt, $reasons);

            // Reprise : le brouillon rejeté et ses motifs, à la suite de la conversation
            $request = new ChatRequest($request->system, [
                ...$prepared->request->messages,
                new ChatMessage(ChatRole::Assistant, $response->text),
                new ChatMessage(ChatRole::User, $this->retryInstruction($reasons)),
            ], $request->outputSchema);
        }

        return $this->answer($turn, [Block::text(self::DEGRADED_REPLY)], $trace, degraded: true);
    }

    /**
     * @return array{list<Block>, list<string>} blocs du brouillon et motifs de rejet
     */
    private function check(ChatResponse $response): array
    {
        try {
            $blocks = AnswerFormat::parse($response->text);
        } catch (\JsonException) {
            return [[], [$response->stopReason === ChatStopReason::MaxTokens
                ? 'La réponse a été coupée avant la fin : fais plus court.'
                : 'La réponse ne respecte pas le format JSON demandé.']];
        }

        // Aucune source ni aucun calcul n'est encore lu pendant un tour (lots L10 et L14)
        return [$blocks, DraftValidator::check($blocks, evidence: [])];
    }

    /** @param list<string> $reasons */
    private function retryInstruction(array $reasons): string
    {
        return "[Contrôle automatique d'Hector, pas un message de l'utilisateur]\n"
            . "Ta réponse précédente a été rejetée et n'a pas été montrée à l'utilisateur, pour ces raisons :\n- "
            . implode("\n- ", $reasons)
            . "\nRéponds de nouveau à son dernier message, au même format, en corrigeant ces points.";
    }

    /** @param list<Block> $blocks */
    private function answer(Turn $turn, array $blocks, TraceRecorder $trace, bool $degraded = false): Turn
    {
        /** @var Turn $answered */
        $answered = $this->commandBus->handle(new AnswerTurnCommand($turn->getUuid(), $blocks, $degraded, $trace->trace()));

        return $answered;
    }

    private function fail(Turn $turn, ChatModelException $e, TraceRecorder $trace): Turn
    {
        $failure = $e instanceof ChatModelUnavailableException ? TurnFailure::Unavailable : TurnFailure::Error;

        /** @var Turn $failed */
        $failed = $this->commandBus->handle(new FailTurnCommand($turn->getUuid(), $failure, $trace->trace(mb_substr($e->getMessage(), 0, 255))));

        return $failed;
    }
}
