<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository\Calculation;

use App\Domain\Calculation\CalculationRun;
use App\Domain\Calculation\Repository\CalculationRunRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CalculationRun>
 */
final class CalculationRunRepository extends ServiceEntityRepository implements CalculationRunRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CalculationRun::class);
    }

    public function add(CalculationRun $run): CalculationRun
    {
        $this->getEntityManager()->persist($run);

        return $run;
    }

    public function findOneByUuid(string $uuid): ?CalculationRun
    {
        return $this->find($uuid);
    }

    public function transferOwnership(string $fromOwnerUuid, string $toOwnerUuid): void
    {
        $this->getEntityManager()->createQueryBuilder()
            ->update(CalculationRun::class, 'r')
            ->set('r.ownerUuid', ':to')
            ->where('r.ownerUuid = :from')
            ->setParameter('to', $toOwnerUuid)
            ->setParameter('from', $fromOwnerUuid)
            ->getQuery()
            ->execute();
    }

    public function removeAllByOwner(string $ownerUuid): void
    {
        $this->getEntityManager()->createQueryBuilder()
            ->delete(CalculationRun::class, 'r')
            ->where('r.ownerUuid = :owner')
            ->setParameter('owner', $ownerUuid)
            ->getQuery()
            ->execute();
    }
}
