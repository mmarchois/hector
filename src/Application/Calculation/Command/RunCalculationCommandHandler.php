<?php

declare(strict_types=1);

namespace App\Application\Calculation\Command;

use App\Domain\Calculation\CalculationEngine;
use App\Domain\Calculation\CalculationRun;
use App\Domain\Calculation\CalculatorCatalog;
use App\Domain\Calculation\Exception\CalculationRefusedException;
use App\Domain\Calculation\Exception\CalculatorNotFoundException;
use App\Domain\Calculation\Repository\CalculationRunRepositoryInterface;
use App\Domain\Port\DateUtilsInterface;
use App\Domain\Port\IdFactoryInterface;

/** Fait un calcul et l'enregistre. Un calcul refusé ne laisse aucune trace : il n'a pas de résultat. */
final readonly class RunCalculationCommandHandler
{
    public function __construct(
        private CalculatorCatalog $catalog,
        private CalculationRunRepositoryInterface $runRepository,
        private IdFactoryInterface $idFactory,
        private DateUtilsInterface $dateUtils,
    ) {
    }

    /**
     * @throws CalculatorNotFoundException
     * @throws CalculationRefusedException
     */
    public function __invoke(RunCalculationCommand $command): CalculationRun
    {
        $result = CalculationEngine::run($this->catalog->get((string) $command->calculator), $command->inputs ?? []);

        return $this->runRepository->add(
            new CalculationRun($this->idFactory->make(), $command->ownerUuid, $result, $this->dateUtils->getNow()),
        );
    }
}
