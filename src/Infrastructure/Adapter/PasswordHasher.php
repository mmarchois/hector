<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Domain\Port\PasswordHasherInterface;
use App\Domain\User\User;
use App\Infrastructure\Security\User\SecurityUser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class PasswordHasher implements PasswordHasherInterface
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    public function hash(User $user, string $plainPassword): string
    {
        return $this->hasher->hashPassword(new SecurityUser($user), $plainPassword);
    }

    public function isValid(User $user, string $plainPassword): bool
    {
        return $this->hasher->isPasswordValid(new SecurityUser($user), $plainPassword);
    }
}
