<?php

declare(strict_types=1);

namespace App\Application\Conversation\Service;

use App\Application\Conversation\Port\ParticipantInterface;
use App\Domain\Conversation\Exception\FreeAllowanceExhaustedException;
use App\Domain\Conversation\Repository\TurnRepositoryInterface;

/**
 * Allocation gratuite selon le type de compte (règle CV8, question P10, décision D17) :
 * un nombre de messages au total pour un invité, par période de 24 heures pour un compte.
 * Les valeurs sont dans config/services.yaml ; le paiement n'est pas encore conçu.
 */
final readonly class FreeAllowancePolicy
{
    public function __construct(
        private ParticipantInterface $participants,
        private TurnRepositoryInterface $turnRepository,
        private int $guestMessages,
        private int $accountMessagesPerDay,
    ) {
    }

    /** @throws FreeAllowanceExhaustedException */
    public function assertAllowed(string $ownerUuid, \DateTimeImmutable $now): void
    {
        if ($this->participants->profileOf($ownerUuid)->guest) {
            if ($this->turnRepository->countByOwner($ownerUuid) >= $this->guestMessages) {
                throw new FreeAllowanceExhaustedException(signupRequired: true);
            }

            return;
        }

        if ($this->turnRepository->countByOwner($ownerUuid, $now->modify('-24 hours')) >= $this->accountMessagesPerDay) {
            throw new FreeAllowanceExhaustedException(signupRequired: false);
        }
    }
}
