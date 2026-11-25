<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Command;

use App\Application\CommandInterface;

/** Retire une version publiée : elle sort de la recherche, ses passages restent consultables (KN4). */
final readonly class WithdrawDocumentVersionCommand implements CommandInterface
{
    public function __construct(
        public string $versionUuid,
    ) {
    }
}
