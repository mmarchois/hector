<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Domain\Port\DateUtilsInterface;
use App\Domain\User\Repository\RefreshTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;

final readonly class PurgeGuestsCommandHandler
{
    /**
     * Délai laissé à un invité tout juste créé pour recevoir sa première session :
     * la purge ne doit jamais emporter un compte en cours d'ouverture.
     */
    private const string GRACE_PERIOD = '-1 day';

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private DateUtilsInterface $dateUtils,
    ) {
    }

    /** @return int nombre d'invités supprimés */
    public function __invoke(PurgeGuestsCommand $command): int
    {
        $now = $this->dateUtils->getNow();
        $guests = $this->userRepository->findGuestsWithoutSession($now, $now->modify(self::GRACE_PERIOD));

        foreach ($guests as $guest) {
            // Les sessions sont toutes expirées ; on retire aussi leurs traces
            $this->refreshTokenRepository->removeAllByUser($guest->getUuid());
            $this->userRepository->remove($guest);
        }

        return \count($guests);
    }
}
