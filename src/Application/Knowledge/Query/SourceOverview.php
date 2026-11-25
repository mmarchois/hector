<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Query;

use App\Domain\Knowledge\DocumentVersion;
use App\Domain\Knowledge\Source;

final readonly class SourceOverview
{
    /**
     * @param list<DocumentVersion> $versions de la plus récente à la plus ancienne
     */
    public function __construct(
        public Source $source,
        public array $versions,
    ) {
    }
}
