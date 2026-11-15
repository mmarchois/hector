<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository\Company;

use App\Domain\Company\Company;
use App\Domain\Company\Repository\CompanyRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Company>
 */
final class CompanyRepository extends ServiceEntityRepository implements CompanyRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    public function add(Company $company): Company
    {
        $this->getEntityManager()->persist($company);

        return $company;
    }

    public function findOneBySiret(string $siret): ?Company
    {
        return $this->findOneBy(['siret' => $siret]);
    }
}
