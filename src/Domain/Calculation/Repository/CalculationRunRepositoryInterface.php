<?php

declare(strict_types=1);

namespace App\Domain\Calculation\Repository;

use App\Domain\Calculation\CalculationRun;

interface CalculationRunRepositoryInterface
{
    public function add(CalculationRun $run): CalculationRun;

    public function findOneByUuid(string $uuid): ?CalculationRun;

    /** Rattache à $toOwnerUuid les calculs de $fromOwnerUuid (fusion d'un invité). */
    public function transferOwnership(string $fromOwnerUuid, string $toOwnerUuid): void;

    /** Efface les calculs d'un utilisateur (suppression de compte). */
    public function removeAllByOwner(string $ownerUuid): void;
}
