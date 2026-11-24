<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

final readonly class Prompt
{
    public function __construct(
        public string $name,
        /** Empreinte du texte : la trace d'un tour dit quelle version des consignes a servi */
        public string $version,
        public string $text,
    ) {
    }
}
