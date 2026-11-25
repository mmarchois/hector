<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Port;

final readonly class StoredFile
{
    public function __construct(
        public string $key,
        /** Empreinte SHA-256 du contenu */
        public string $fingerprint,
    ) {
    }
}
