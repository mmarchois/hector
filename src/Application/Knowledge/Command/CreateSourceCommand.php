<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Command;

use App\Application\CommandInterface;

/** Déclare une source documentaire, avec son corpus et sa licence (KN5, KN7). */
final readonly class CreateSourceCommand implements CommandInterface
{
    /**
     * @param list<string> $domains
     */
    public function __construct(
        public string $corpus,
        public string $title,
        public string $publisher,
        public string $kind,
        public bool $indexingAllowed,
        public bool $excerptAllowed,
        public ?int $excerptMaxCharacters,
        public bool $thirdPartyTransferAllowed,
        public string $licenseOrigin,
        public array $domains = [],
    ) {
    }
}
