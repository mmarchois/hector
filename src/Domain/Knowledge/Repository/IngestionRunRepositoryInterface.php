<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Repository;

use App\Domain\Knowledge\DocumentVersion;
use App\Domain\Knowledge\IngestionRun;

interface IngestionRunRepositoryInterface
{
    public function add(IngestionRun $run): IngestionRun;

    public function findLatestByVersion(DocumentVersion $version): ?IngestionRun;
}
