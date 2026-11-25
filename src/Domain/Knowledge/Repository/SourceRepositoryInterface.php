<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Repository;

use App\Domain\Knowledge\Source;

interface SourceRepositoryInterface
{
    public function add(Source $source): Source;

    public function findOneByUuid(string $uuid): ?Source;

    /** @return list<Source> par corpus puis par titre */
    public function findAllOrdered(): array;
}
