<?php

declare(strict_types=1);

namespace App\Application\Conversation\Service;

use App\Domain\Conversation\Exception\ServiceSaturatedException;
use App\Domain\Conversation\Repository\TurnRepositoryInterface;

/**
 * Budget global de jetons sur 24 heures glissantes, tous utilisateurs confondus (règle CV9) :
 * le coupe-circuit qui borne la facture du fournisseur, quoi qu'il arrive aux allocations
 * individuelles (comptes invités en masse, erreur de configuration, abus).
 * La valeur vient de CHAT_DAILY_TOKEN_BUDGET.
 */
final readonly class GlobalTokenBudget
{
    public function __construct(
        private TurnRepositoryInterface $turnRepository,
        private int $tokensPerDay,
    ) {
    }

    /** @throws ServiceSaturatedException */
    public function assertAvailable(\DateTimeImmutable $now): void
    {
        if ($this->turnRepository->sumTokens($now->modify('-24 hours')) >= $this->tokensPerDay) {
            throw new ServiceSaturatedException();
        }
    }
}
