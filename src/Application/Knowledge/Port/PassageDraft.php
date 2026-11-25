<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Port;

/** Passage issu du découpage, avant enregistrement : son texte et sa localisation (KN3). */
final readonly class PassageDraft
{
    public function __construct(
        public ?int $page,
        public ?string $section,
        public ?string $article,
        public string $text,
    ) {
    }
}
