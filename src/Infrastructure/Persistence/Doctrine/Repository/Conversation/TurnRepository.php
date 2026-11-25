<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository\Conversation;

use App\Domain\Conversation\Repository\TurnRepositoryInterface;
use App\Domain\Conversation\Turn;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Turn>
 */
final class TurnRepository extends ServiceEntityRepository implements TurnRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Turn::class);
    }

    public function add(Turn $turn): Turn
    {
        $this->getEntityManager()->persist($turn);

        return $turn;
    }

    public function findOneByUuid(string $uuid): ?Turn
    {
        return $this->find($uuid);
    }

    public function countByOwner(string $ownerUuid, ?\DateTimeImmutable $since = null): int
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->select('COUNT(t.uuid)')
            ->join('t.conversation', 'c')
            ->where('c.ownerUuid = :owner')
            ->setParameter('owner', $ownerUuid);

        if ($since !== null) {
            $queryBuilder->andWhere('t.createdAt >= :since')->setParameter('since', $since);
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }
}
