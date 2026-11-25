<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Repository;

use App\Domain\Knowledge\DocumentVersion;
use App\Domain\Knowledge\Passage;

interface PassageRepositoryInterface
{
    public function add(Passage $passage): Passage;

    public function findOneByUuid(string $uuid): ?Passage;

    /** Passages d'un brouillon, avant une nouvelle ingestion. */
    public function removeByVersion(DocumentVersion $version): void;

    /** @return list<Passage> dans l'ordre du document */
    public function findByVersion(DocumentVersion $version): array;

    public function countByVersion(DocumentVersion $version): int;
}
