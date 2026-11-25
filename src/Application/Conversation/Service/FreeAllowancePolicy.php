<?php

declare(strict_types=1);

namespace App\Application\Conversation\Service;

use App\Application\Conversation\Port\ParticipantInterface;
use App\Domain\Conversation\Exception\FreeAllowanceExhaustedException;
use App\Domain\Conversation\Repository\TurnRepositoryInterface;

/**
 * Allocation gratuite selon le type de compte (règle CV8, question P10, décision D17) :
 * au total pour un invité, par période de 24 heures pour un compte. Elle se compte en
 * messages, la mesure que l'utilisateur comprend, et en jetons, la mesure du coût : de
 * longues réponses épuisent l'allocation avant le nombre de messages.
 * Les valeurs sont dans config/services.yaml ; le paiement n'est pas encore conçu.
 */
final readonly class FreeAllowancePolicy
{
    public function __construct(
        private ParticipantInterface $participants,
        private TurnRepositoryInterface $turnRepository,
        private int $guestMessages,
        private int $accountMessagesPerDay,
        private int $guestTokens,
        private int $accountTokensPerDay,
    ) {
    }

    /** @throws FreeAllowanceExhaustedException */
    public function assertAllowed(string $ownerUuid, \DateTimeImmutable $now): void
    {
        if ($this->participants->profileOf($ownerUuid)->guest) {
            if ($this->turnRepository->countByOwner($ownerUuid) >= $this->guestMessages
                || $this->turnRepository->sumTokensByOwner($ownerUuid) >= $this->guestTokens) {
                throw new FreeAllowanceExhaustedException(signupRequired: true);
            }

            return;
        }

        $since = $now->modify('-24 hours');
        if ($this->turnRepository->countByOwner($ownerUuid, $since) >= $this->accountMessagesPerDay
            || $this->turnRepository->sumTokensByOwner($ownerUuid, $since) >= $this->accountTokensPerDay) {
            throw new FreeAllowanceExhaustedException(signupRequired: false);
        }
    }
}
