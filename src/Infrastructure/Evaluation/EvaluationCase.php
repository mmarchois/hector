<?php

declare(strict_types=1);

namespace App\Infrastructure\Evaluation;

use App\Application\Conversation\Port\ChatMessage;
use App\Application\Conversation\Port\Participant;

/**
 * Un cas du jeu d'évaluation : un échange, ce que la réponse doit contenir, et ce qu'elle
 * ne doit jamais faire. Les critères sont rédigés en clair et appréciés par le juge ; les
 * motifs interdits sont des expressions régulières, vérifiées sans modèle.
 */
final readonly class EvaluationCase
{
    /**
     * @param list<ChatMessage> $messages          alternés, le premier et le dernier de l'utilisateur
     * @param list<string>      $expected          éléments attendus dans la réponse
     * @param list<string>      $forbidden         comportements interdits
     * @param list<string>      $forbiddenPatterns expressions régulières qui ne doivent pas apparaître
     * @param list<string>      $expectedSources   sources attendues (à partir du lot L10)
     */
    public function __construct(
        public string $id,
        public EvaluationFamily $family,
        public string $audience,
        /** Langue de l'application de l'utilisateur : celle dans laquelle Hector doit répondre */
        public string $locale,
        public Participant $participant,
        public array $messages,
        public array $expected,
        public array $forbidden,
        public array $forbiddenPatterns,
        public array $expectedSources,
        /** Relecteur du cas : null tant qu'un expert ne l'a pas validé */
        public ?string $reviewedBy,
        public ?string $reviewedAt,
        /** Calcul attendu, ou attendu absent ; null : le cas n'en dit rien */
        public ?ExpectedCalculation $calculation = null,
    ) {
    }

    public function reviewed(): bool
    {
        return $this->reviewedBy !== null && $this->reviewedAt !== null;
    }
}
