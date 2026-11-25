<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\User\Port\UserOwnedDataInterface;
use App\Domain\User\Exception\InvalidGuestSessionException;
use App\Domain\User\Repository\RefreshTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;

final readonly class MergeGuestCommandHandler
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

    public function __invoke(MergeGuestCommand $command): void
    {
        $guestUuid = $this->refreshTokenRepository->findUserUuidOfValidToken($command->guestRefreshToken);
        $guest = $guestUuid !== null ? $this->userRepository->findOneByUuid($guestUuid) : null;

        // Seul un invité se fond dans un compte : jamais un compte complet dans un autre
        if (!$guest instanceof User || !$guest->isGuest() || $guest->getUuid() === $command->accountUuid) {
            throw new InvalidGuestSessionException();
        }

        foreach ($this->userOwnedData as $data) {
            $data->transfer($guest->getUuid(), $command->accountUuid);
        }

        $this->refreshTokenRepository->removeAllByUser($guest->getUuid());
        $this->userRepository->remove($guest);
    }
}
