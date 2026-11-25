<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Calculation;

use App\Domain\Calculation\CalculationEngine;
use App\Domain\Calculation\CalculationResult;
use App\Domain\Calculation\CalculationRun;
use App\Domain\Calculation\Calculator\ThreePhaseCurrent;
use PHPUnit\Framework\TestCase;

final class CalculationRunTest extends TestCase
{
    private CalculationResult $result;

    protected function setUp(): void
    {
        $this->result = CalculationEngine::run(new ThreePhaseCurrent(), [
            'power' => ['value' => 10, 'unit' => 'kW'],
            'voltage' => ['value' => 400, 'unit' => 'V'],
            'powerFactor' => ['value' => 0.85],
        ]);
    }

    public function testARunKeepsWhoAskedWhenAndWithWhichVersionOfTheCalculator(): void
    {
        $createdAt = new \DateTimeImmutable('2026-11-25 10:00:00');

        $run = new CalculationRun('run-1', 'owner', $this->result, $createdAt);

        self::assertSame('run-1', $run->getUuid());
        self::assertSame('owner', $run->getOwnerUuid());
        self::assertTrue($run->isOwnedBy('owner'));
        self::assertFalse($run->isOwnedBy('intrus'));
        self::assertSame('three_phase_current', $run->getCalculatorKey());
        self::assertSame(1, $run->getCalculatorVersion());
        self::assertSame($createdAt, $run->getCreatedAt());
    }

    public function testCa5ARunIsReadBackAsItWasMade(): void
    {
        $run = new CalculationRun('run-1', 'owner', $this->result, new \DateTimeImmutable());

        // Définition d'alors, entrées, étapes, résultat : rien ne dépend du calculateur d'aujourd'hui
        self::assertEquals($this->result, $run->getResult());
        self::assertSame(16.99, $run->getResult()->result->value);
    }

    public function testTheCopyOfAResultSurvivesItsStorageAsJson(): void
    {
        // Avec ou sans le « .0 » des nombres entiers, selon la façon dont la base les rend
        foreach ([\JSON_THROW_ON_ERROR, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION] as $flags) {
            $stored = json_decode(json_encode($this->result->toArray(), $flags), true, flags: \JSON_THROW_ON_ERROR);

            self::assertEquals($this->result, CalculationResult::fromArray($stored));
        }
    }
}
