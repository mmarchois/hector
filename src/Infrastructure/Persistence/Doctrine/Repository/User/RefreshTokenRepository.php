<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository\User;

use App\Domain\User\Repository\RefreshTokenRepositoryInterface;
use App\Infrastructure\Security\RefreshToken;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function removeAllByUser(string $userUuid): void
    {
        // `username` porte l'uuid de l'utilisateur (cf. SecurityUser::getUserIdentifier())
        $this->entityManager->createQueryBuilder()
            ->delete(RefreshToken::class, 'rt')
            ->where('rt.username = :username')
            ->setParameter('username', $userUuid)
            ->getQuery()
            ->execute();
    }
}
