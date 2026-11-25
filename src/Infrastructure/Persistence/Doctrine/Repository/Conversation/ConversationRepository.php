<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository\Conversation;

use App\Domain\Conversation\Conversation;
use App\Domain\Conversation\Repository\ConversationRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
final class ConversationRepository extends ServiceEntityRepository implements ConversationRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    public function add(Conversation $conversation): Conversation
    {
        $this->getEntityManager()->persist($conversation);

        return $conversation;
    }

    public function findOneByUuid(string $uuid): ?Conversation
    {
        return $this->find($uuid);
    }

    public function findPageByOwner(string $ownerUuid, int $page, int $perPage): array
    {
        /* @var list<Conversation> */
        return $this->createQueryBuilder('c')
            ->where('c.ownerUuid = :owner')
            ->setParameter('owner', $ownerUuid)
            ->orderBy('c.updatedAt', 'DESC')
            ->addOrderBy('c.uuid', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function countByOwner(string $ownerUuid): int
    {
        return $this->count(['ownerUuid' => $ownerUuid]);
    }

    public function transferOwnership(string $fromOwnerUuid, string $toOwnerUuid): void
    {
        $this->getEntityManager()->createQueryBuilder()
            ->update(Conversation::class, 'c')
            ->set('c.ownerUuid', ':to')
            ->where('c.ownerUuid = :from')
            ->setParameter('to', $toOwnerUuid)
            ->setParameter('from', $fromOwnerUuid)
            ->getQuery()
            ->execute();
    }

    public function removeAllByOwner(string $ownerUuid): void
    {
        // Messages et tours suivent par les clés étrangères ON DELETE CASCADE
        $this->getEntityManager()->createQueryBuilder()
            ->delete(Conversation::class, 'c')
            ->where('c.ownerUuid = :owner')
            ->setParameter('owner', $ownerUuid)
            ->getQuery()
            ->execute();
    }
}
