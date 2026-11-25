<?php

declare(strict_types=1);

namespace App\Application\Conversation\Service;

use App\Domain\Conversation\Block;
use App\Domain\Conversation\TurnFailure;
use App\Domain\Conversation\TurnTrace;

/** Issue d'un tour, avant tout enregistrement : une réponse, ou un échec du fournisseur. */
final readonly class TurnOutcome
{
    /**
     * @param list<Block> $blocks réponse à montrer ; vide si le tour a échoué
     */
    public function __construct(
        public array $blocks,
        /** Réponse fixe du serveur : refus, réponse vide, ou brouillons rejetés deux fois */
        public bool $degraded,
        public ?TurnFailure $failure,
        public TurnTrace $trace,
    ) {
    }

    public function failed(): bool
    {
        return $this->failure !== null;
    }
}
