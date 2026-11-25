<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Command;

use App\Application\CommandInterface;

/** Publie une version relue par un humain (KN1) : elle entre dans la recherche et ne change plus. */
final readonly class PublishDocumentVersionCommand implements CommandInterface
{
    public function __construct(
        public string $versionUuid,
        public string $reviewer,
    ) {
    }
}
