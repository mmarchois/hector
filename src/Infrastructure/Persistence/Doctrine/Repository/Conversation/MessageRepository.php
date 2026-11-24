<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository\Conversation;

use App\Domain\Conversation\Conversation;
use App\Domain\Conversation\Message;
use App\Domain\Conversation\Repository\MessageRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
final class MessageRepository extends ServiceEntityRepository implements MessageRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function add(Message $message): Message
    {
        $this->getEntityManager()->persist($message);

        return $message;
    }

    public function findPage(Conversation $conversation, ?string $beforeUuid, int $limit): array
    {
        $queryBuilder = $this->createQueryBuilder('m')
            ->where('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.uuid', 'DESC')
            ->setMaxResults($limit);

        if ($beforeUuid !== null) {
            $queryBuilder->andWhere('m.uuid < :before')->setParameter('before', $beforeUuid);
        }

        /** @var list<Message> $latestFirst */
        $latestFirst = $queryBuilder->getQuery()->getResult();

        return array_reverse($latestFirst);
    }

    public function existsBefore(Conversation $conversation, string $uuid): bool
    {
        return $this->createQueryBuilder('m')
            ->select('1')
            ->where('m.conversation = :conversation')
            ->andWhere('m.uuid < :uuid')
            ->setParameter('conversation', $conversation)
            ->setParameter('uuid', $uuid)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }
}
