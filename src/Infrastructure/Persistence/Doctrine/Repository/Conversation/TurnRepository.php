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
    /** Somme des jetons de la trace. Un tour encore en cours n'a pas de trace : il compte pour zéro jusqu'à sa fin. */
    private const string TOKENS = 'COALESCE(SUM(COALESCE(t.trace.inputTokens, 0) + COALESCE(t.trace.outputTokens, 0) + COALESCE(t.trace.cacheReadInputTokens, 0) + COALESCE(t.trace.cacheCreationInputTokens, 0)), 0)';

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

    public function sumTokensByOwner(string $ownerUuid, ?\DateTimeImmutable $since = null): int
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->select(self::TOKENS)
            ->join('t.conversation', 'c')
            ->where('c.ownerUuid = :owner')
            ->setParameter('owner', $ownerUuid);

        if ($since !== null) {
            $queryBuilder->andWhere('t.createdAt >= :since')->setParameter('since', $since);
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    public function sumTokens(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select(self::TOKENS)
            ->where('t.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
