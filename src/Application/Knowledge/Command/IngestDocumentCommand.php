<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Command;

use App\Application\CommandInterface;

/**
 * Ingère un fichier comme version d'une source : la version naît en brouillon, à relire
 * avant publication. Réingérer sous le même libellé remplace les passages du brouillon.
 */
final readonly class IngestDocumentCommand implements CommandInterface
{
    public function __construct(
        public string $sourceUuid,
        public string $filePath,
        public string $label,
        /** Dates au format AAAA-MM-JJ */
        public ?string $publishedOn = null,
        public ?string $effectiveFrom = null,
        public ?string $effectiveUntil = null,
        public ?string $replacesUuid = null,
    ) {
    }
}
