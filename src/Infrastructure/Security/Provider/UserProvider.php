<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Provider;

use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;
use App\Infrastructure\Security\User\SecurityUser;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements UserProviderInterface<SecurityUser>
 */
final readonly class UserProvider implements UserProviderInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * L'identifiant est un email à la connexion (json_login), un uuid ensuite
     * (JWT et refresh token, cf. SecurityUser::getUserIdentifier()).
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = Uuid::isValid($identifier)
            ? $this->userRepository->findOneByUuid($identifier)
            : $this->userRepository->findOneByEmail($identifier);

        if (!$user instanceof User) {
            throw new UserNotFoundException(\sprintf('User "%s" not found.', $identifier));
        }

        return new SecurityUser($user);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === SecurityUser::class;
    }
}
