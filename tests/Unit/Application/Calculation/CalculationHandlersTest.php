<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Calculation;

use App\Application\Calculation\Command\RunCalculationCommand;
use App\Application\Calculation\Command\RunCalculationCommandHandler;
use App\Application\Calculation\Query\GetCalculationRunQuery;
use App\Application\Calculation\Query\GetCalculationRunQueryHandler;
use App\Application\Calculation\Query\ListCalculatorsQuery;
use App\Application\Calculation\Query\ListCalculatorsQueryHandler;
use App\Domain\Calculation\CalculationEngine;
use App\Domain\Calculation\CalculationRun;
use App\Domain\Calculation\Calculator\OhmVoltage;
use App\Domain\Calculation\CalculatorCatalog;
use App\Domain\Calculation\CalculatorDefinition;
use App\Domain\Calculation\Exception\CalculationRefusedException;
use App\Domain\Calculation\Exception\CalculationRunNotFoundException;
use App\Domain\Calculation\Exception\CalculatorNotFoundException;
use App\Domain\Calculation\Repository\CalculationRunRepositoryInterface;
use App\Domain\Port\DateUtilsInterface;
use App\Domain\Port\IdFactoryInterface;
use PHPUnit\Framework\TestCase;

final class CalculationHandlersTest extends TestCase
{
    private const array INPUTS = ['resistance' => ['value' => 23, 'unit' => 'Ω'], 'current' => ['value' => 10, 'unit' => 'A']];

    public function testACalculationIsRunAndRecordedForTheOneWhoAsked(): void
    {
        $now = new \DateTimeImmutable('2026-11-25 10:00:00');
        $repository = $this->createMock(CalculationRunRepositoryInterface::class);
        $repository->expects(self::once())->method('add')->willReturnArgument(0);

        $run = $this->runHandler($repository, $now)(new RunCalculationCommand('owner', 'ohm_voltage', self::INPUTS));

        self::assertSame('run-uuid', $run->getUuid());
        self::assertSame('owner', $run->getOwnerUuid());
        self::assertSame('ohm_voltage', $run->getCalculatorKey());
        self::assertSame(1, $run->getCalculatorVersion());
        self::assertSame($now, $run->getCreatedAt());
        self::assertSame(230.0, $run->getResult()->result->value);
    }

    public function testAnUnknownCalculatorRecordsNothing(): void
    {
        $repository = $this->createMock(CalculationRunRepositoryInterface::class);
        $repository->expects(self::never())->method('add');

        $this->expectException(CalculatorNotFoundException::class);

        $this->runHandler($repository)(new RunCalculationCommand('owner', 'voltage_drop', self::INPUTS));
    }

    public function testARefusedCalculationRecordsNothing(): void
    {
        $repository = $this->createMock(CalculationRunRepositoryInterface::class);
        $repository->expects(self::never())->method('add');

        $this->expectException(CalculationRefusedException::class);

        // Sans entrées du tout : chacune est réclamée, aucune n'est supposée
        $this->runHandler($repository)(new RunCalculationCommand('owner', 'ohm_voltage', null));
    }

    public function testOnlyTheOneWhoAskedReadsACalculation(): void
    {
        $run = new CalculationRun('run-1', 'owner', CalculationEngine::run(new OhmVoltage(), self::INPUTS), new \DateTimeImmutable());
        $repository = $this->createStub(CalculationRunRepositoryInterface::class);
        $repository->method('findOneByUuid')->willReturnCallback(static fn (string $uuid): ?CalculationRun => $uuid === 'run-1' ? $run : null);
        $handler = new GetCalculationRunQueryHandler($repository);

        self::assertSame($run, $handler(new GetCalculationRunQuery('run-1', 'owner')));

        foreach ([new GetCalculationRunQuery('run-1', 'intrus'), new GetCalculationRunQuery('inconnu', 'owner')] as $query) {
            try {
                $handler($query);
                self::fail('Ce calcul ne devait pas être lisible.');
            } catch (CalculationRunNotFoundException $e) {
                self::assertSame('Calcul introuvable.', $e->getMessage());
            }
        }
    }

    public function testTheCalculatorsAreListedInTheirPresentationOrder(): void
    {
        $definitions = (new ListCalculatorsQueryHandler(new CalculatorCatalog()))(new ListCalculatorsQuery());

        self::assertSame(
            ['ohm_voltage', 'ohm_current', 'ohm_resistance', 'single_phase_power', 'three_phase_power', 'single_phase_current', 'three_phase_current'],
            array_map(static fn (CalculatorDefinition $definition): string => $definition->key, $definitions),
        );
    }

    private function runHandler(CalculationRunRepositoryInterface $repository, ?\DateTimeImmutable $now = null): RunCalculationCommandHandler
    {
        $idFactory = $this->createStub(IdFactoryInterface::class);
        $idFactory->method('make')->willReturn('run-uuid');
        $dateUtils = $this->createStub(DateUtilsInterface::class);
        $dateUtils->method('getNow')->willReturn($now ?? new \DateTimeImmutable());

        return new RunCalculationCommandHandler(new CalculatorCatalog(), $repository, $idFactory, $dateUtils);
    }
}
