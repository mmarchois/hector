<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\User\Port\UserOwnedDataInterface;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\RefreshTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;

final readonly class DeleteUserCommandHandler
{
    /**
     * @param iterable<UserOwnedDataInterface> $userOwnedData
     */
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private iterable $userOwnedData,
    ) {
    }

    public function __invoke(DeleteUserCommand $command): void
    {
        $user = $this->userRepository->findOneByUuid($command->userUuid);
        if (!$user instanceof User) {
            throw new UserNotFoundException();
        }

        // RGPD, droit à l'effacement : données des autres contextes (conversations…),
        // sessions mobiles révoquées, puis compte supprimé
        foreach ($this->userOwnedData as $data) {
            $data->erase($user->getUuid());
        }
        $this->refreshTokenRepository->removeAllByUser($user->getUuid());
        $this->userRepository->remove($user);
    }
}
