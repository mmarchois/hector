<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository\User;

use App\Domain\User\Repository\RefreshTokenRepositoryInterface;
use App\Infrastructure\Security\RefreshToken;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;

final readonly class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        // Gestionnaire de gesdinet : seule l'empreinte du jeton est stockée, il sait la calculer
        private RefreshTokenManagerInterface $refreshTokenManager,
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

    public function findUserUuidOfValidToken(string $refreshToken): ?string
    {
        $token = $this->refreshTokenManager->get($refreshToken);

        return $token !== null && $token->isValid() ? $token->getUsername() : null;
    }
}
