<?php

declare(strict_types=1);

namespace App\Domain\Conversation;

/**
 * Trace d'un tour (CV6) : ce qui permet d'expliquer une réponse. Écrite une fois, à la fin
 * du tour. Les lots suivants y ajoutent passages lus, outils appelés et contrôles.
 */
final readonly class TurnTrace
{
    public function __construct(
        public ?string $model = null,
        public ?string $promptVersion = null,
        public ?string $stopReason = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public ?int $cacheReadInputTokens = null,
        public ?int $cacheCreationInputTokens = null,
        public ?int $durationMs = null,
        /** Détail technique d'un échec (classe d'erreur du fournisseur), jamais montré à l'utilisateur */
        public ?string $error = null,
    ) {
    }
}
