<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository\Knowledge;

use App\Domain\Knowledge\Repository\SourceRepositoryInterface;
use App\Domain\Knowledge\Source;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Source>
 */
final class SourceRepository extends ServiceEntityRepository implements SourceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Source::class);
    }

    public function add(Source $source): Source
    {
        $this->getEntityManager()->persist($source);

        return $source;
    }

    public function findOneByUuid(string $uuid): ?Source
    {
        return $this->find($uuid);
    }

    public function findAllOrdered(): array
    {
        /* @var list<Source> */
        return $this->createQueryBuilder('s')
            ->orderBy('s.corpus', 'ASC')
            ->addOrderBy('s.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
