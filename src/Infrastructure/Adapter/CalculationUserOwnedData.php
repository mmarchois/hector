<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Application\User\Port\UserOwnedDataInterface;
use App\Domain\Calculation\Repository\CalculationRunRepositoryInterface;

/**
 * Les calculs d'un utilisateur, vus du contexte User. Le pont vit dans l'infrastructure :
 * ni User ni Calculation ne dépendent l'un de l'autre.
 */
final readonly class CalculationUserOwnedData implements UserOwnedDataInterface
{
    public function __construct(
        private CalculationRunRepositoryInterface $runRepository,
    ) {
    }

    public function transfer(string $fromUserUuid, string $toUserUuid): void
    {
        $this->runRepository->transferOwnership($fromUserUuid, $toUserUuid);
    }

    public function erase(string $userUuid): void
    {
        $this->runRepository->removeAllByOwner($userUuid);
    }
}
