<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Port;

use App\Domain\Knowledge\Passage;

final readonly class PassageHit
{
    public function __construct(
        public Passage $passage,
        public float $score,
        /** La requête contient un identifiant (référence, numéro) présent tel quel dans le passage */
        public bool $exactIdentifier,
    ) {
    }
}
