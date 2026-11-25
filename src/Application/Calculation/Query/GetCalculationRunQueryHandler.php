<?php

declare(strict_types=1);

namespace App\Application\Calculation\Query;

use App\Domain\Calculation\CalculationRun;
use App\Domain\Calculation\Exception\CalculationRunNotFoundException;
use App\Domain\Calculation\Repository\CalculationRunRepositoryInterface;

final readonly class GetCalculationRunQueryHandler
{
    public function __construct(
        private CalculationRunRepositoryInterface $runRepository,
    ) {
    }

    public function __invoke(GetCalculationRunQuery $query): CalculationRun
    {
        $run = $this->runRepository->findOneByUuid($query->runUuid);
        // Le calcul d'un autre n'existe pas pour celui qui le demande
        if (!$run instanceof CalculationRun || !$run->isOwnedBy($query->ownerUuid)) {
            throw new CalculationRunNotFoundException();
        }

        return $run;
    }
}
