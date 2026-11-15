<?php

declare(strict_types=1);

namespace App\Application\User\Query;

use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;

final readonly class GetUserByUuidQueryHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(GetUserByUuidQuery $query): User
    {
        $user = $this->userRepository->findOneByUuid($query->uuid);
        if (!$user instanceof User) {
            throw new UserNotFoundException();
        }

        return $user;
    }
}
