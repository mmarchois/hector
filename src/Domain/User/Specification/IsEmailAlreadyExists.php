<?php

declare(strict_types=1);

namespace App\Domain\User\Specification;

use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;

final readonly class IsEmailAlreadyExists
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function isSatisfiedBy(string $email): bool
    {
        return $this->userRepository->findOneByEmail($email) instanceof User;
    }
}
