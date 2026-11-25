<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Repository;

use App\Domain\Knowledge\DocumentVersion;
use App\Domain\Knowledge\Source;

interface DocumentVersionRepositoryInterface
{
    public function add(DocumentVersion $version): DocumentVersion;

    public function findOneByUuid(string $uuid): ?DocumentVersion;

    /** Nombre de versions publiées, tous corpus confondus. */
    public function countPublished(): int;

    public function findOneBySourceAndLabel(Source $source, string $label): ?DocumentVersion;

    /** @return list<DocumentVersion> de la plus récente à la plus ancienne */
    public function findBySource(Source $source): array;
}
