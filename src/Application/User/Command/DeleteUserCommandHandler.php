<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\RefreshTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;

final readonly class DeleteUserCommandHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
    ) {
    }

    public function __invoke(DeleteUserCommand $command): void
    {
        $user = $this->userRepository->findOneByUuid($command->userUuid);
        if (!$user instanceof User) {
            throw new UserNotFoundException();
        }

        // RGPD, droit à l'effacement : sessions mobiles révoquées puis compte supprimé
        $this->refreshTokenRepository->removeAllByUser($user->getUuid());
        $this->userRepository->remove($user);
    }
}
