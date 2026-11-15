<?php

declare(strict_types=1);

namespace App\Application\Company\Service;

use App\Domain\Company\Company;
use App\Domain\Company\Repository\CompanyRepositoryInterface;
use App\Domain\Port\IdFactoryInterface;

final readonly class CompanyProvider
{
    public function __construct(
        private CompanyRepositoryInterface $companyRepository,
        private IdFactoryInterface $idFactory,
    ) {
    }

    /**
     * Une entreprise est identifiée par son SIRET : celle qui existe déjà est réutilisée
     * telle quelle (collègues d'une même entreprise), sinon elle est créée.
     */
    public function provide(string $name, string $siret, string $vatNumber): Company
    {
        $company = $this->companyRepository->findOneBySiret($siret);
        if ($company instanceof Company) {
            return $company;
        }

        $company = (new Company($this->idFactory->make()))
            ->setName($name)
            ->setSiret($siret)
            ->setVatNumber($vatNumber);
        $this->companyRepository->add($company);

        return $company;
    }
}
