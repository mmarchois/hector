<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Port;

final readonly class ExtractedPage
{
    public function __construct(
        /** Numéro de page dans le document d'origine ; null si le format n'a pas de pages */
        public ?int $number,
        public string $text,
    ) {
    }
}
