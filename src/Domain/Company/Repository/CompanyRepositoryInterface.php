<?php

declare(strict_types=1);

namespace App\Domain\Company\Repository;

use App\Domain\Company\Company;

interface CompanyRepositoryInterface
{
    public function add(Company $company): Company;

    public function findOneBySiret(string $siret): ?Company;
}
