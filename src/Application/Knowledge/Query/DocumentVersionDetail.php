<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Query;

use App\Domain\Knowledge\DocumentVersion;
use App\Domain\Knowledge\IngestionRun;
use App\Domain\Knowledge\Passage;

final readonly class DocumentVersionDetail
{
    /**
     * @param list<Passage> $passages dans l'ordre du document
     */
    public function __construct(
        public DocumentVersion $version,
        public array $passages,
        public ?IngestionRun $lastRun,
    ) {
    }
}
