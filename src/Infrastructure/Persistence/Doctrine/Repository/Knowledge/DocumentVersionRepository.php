<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository\Knowledge;

use App\Domain\Knowledge\DocumentStatus;
use App\Domain\Knowledge\DocumentVersion;
use App\Domain\Knowledge\Repository\DocumentVersionRepositoryInterface;
use App\Domain\Knowledge\Source;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentVersion>
 */
final class DocumentVersionRepository extends ServiceEntityRepository implements DocumentVersionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentVersion::class);
    }

    public function add(DocumentVersion $version): DocumentVersion
    {
        $this->getEntityManager()->persist($version);

        return $version;
    }

    public function findOneByUuid(string $uuid): ?DocumentVersion
    {
        return $this->find($uuid);
    }

    public function countPublished(): int
    {
        return $this->count(['status' => DocumentStatus::Published]);
    }

    public function findOneBySourceAndLabel(Source $source, string $label): ?DocumentVersion
    {
        return $this->findOneBy(['source' => $source, 'label' => $label]);
    }

    public function findBySource(Source $source): array
    {
        /* @var list<DocumentVersion> */
        return $this->createQueryBuilder('v')
            ->where('v.source = :source')
            ->setParameter('source', $source)
            ->orderBy('v.createdAt', 'DESC')
            ->addOrderBy('v.uuid', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
