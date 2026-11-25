<?php

declare(strict_types=1);

namespace App\Application\Conversation\Service;

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
use App\Domain\Conversation\TurnFailure;

/**
 * Cœur d'un tour, sans base de données (docs/ARCHITECTURE.md, § 5, étape 2) : appel au
 * modèle, contrôle déterministe du brouillon, une seule reprise avec les motifs du rejet,
 * puis une réponse dégradée qui le dit. Un brouillon rejeté n'est jamais renvoyé
 * (docs/SECURITY.md, § 4). L'application (TurnRunner) et l'évaluation (lot L8) passent
 * toutes deux par ici : ce qu'on mesure est ce que l'utilisateur reçoit.
 */
final readonly class AnswerEngine
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
        private ChatModelInterface $chatModel,
    ) {
    }

    public function run(PreparedTurn $prepared): TurnOutcome
    {
        $request = new ChatRequest($prepared->request->system, $prepared->request->messages, AnswerFormat::schema());
        $trace = new TraceRecorder($prepared->promptVersion);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            try {
                $response = $this->chatModel->complete($request);
            } catch (ChatModelException $e) {
                return new TurnOutcome(
                    [],
                    degraded: false,
                    failure: $e instanceof ChatModelUnavailableException ? TurnFailure::Unavailable : TurnFailure::Error,
                    trace: $trace->trace(mb_substr($e->getMessage(), 0, 255)),
                );
            }
            $trace->record($response);

            if ($response->stopReason === ChatStopReason::Refusal) {
                return $this->fixed(self::REFUSAL_REPLY, $trace);
            }
            if (trim($response->text) === '') {
                return $this->fixed(self::EMPTY_REPLY, $trace);
            }

            [$blocks, $reasons] = $this->check($response);
            if ($reasons === []) {
                return new TurnOutcome($blocks, degraded: false, failure: null, trace: $trace->trace());
            }
            $trace->reject($attempt, $reasons);

            // Reprise : le brouillon rejeté et ses motifs, à la suite de la conversation
            $request = new ChatRequest($request->system, [
                ...$prepared->request->messages,
                new ChatMessage(ChatRole::Assistant, $response->text),
                new ChatMessage(ChatRole::User, $this->retryInstruction($reasons)),
            ], $request->outputSchema);
        }

        return $this->fixed(self::DEGRADED_REPLY, $trace);
    }

    private function fixed(string $reply, TraceRecorder $trace): TurnOutcome
    {
        return new TurnOutcome([Block::text($reply)], degraded: true, failure: null, trace: $trace->trace());
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
}
