<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository\Knowledge;

use App\Domain\Knowledge\DocumentVersion;
use App\Domain\Knowledge\IngestionRun;
use App\Domain\Knowledge\Repository\IngestionRunRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IngestionRun>
 */
final class IngestionRunRepository extends ServiceEntityRepository implements IngestionRunRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IngestionRun::class);
    }

    public function add(IngestionRun $run): IngestionRun
    {
        $this->getEntityManager()->persist($run);

        return $run;
    }

    public function findLatestByVersion(DocumentVersion $version): ?IngestionRun
    {
        return $this->findOneBy(['version' => $version], ['startedAt' => 'DESC', 'uuid' => 'DESC']);
    }
}
