<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository\Knowledge;

use App\Domain\Knowledge\DocumentVersion;
use App\Domain\Knowledge\Passage;
use App\Domain\Knowledge\Repository\PassageRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Passage>
 */
final class PassageRepository extends ServiceEntityRepository implements PassageRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Passage::class);
    }

    public function add(Passage $passage): Passage
    {
        $this->getEntityManager()->persist($passage);

        return $passage;
    }

    public function removeByVersion(DocumentVersion $version): void
    {
        $this->createQueryBuilder('p')
            ->delete()
            ->where('p.version = :version')
            ->setParameter('version', $version)
            ->getQuery()
            ->execute();
    }

    public function findByVersion(DocumentVersion $version): array
    {
        /* @var list<Passage> */
        return $this->createQueryBuilder('p')
            ->where('p.version = :version')
            ->setParameter('version', $version)
            ->orderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByVersion(DocumentVersion $version): int
    {
        return $this->count(['version' => $version]);
    }
}
