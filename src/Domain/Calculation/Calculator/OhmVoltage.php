<?php

declare(strict_types=1);

namespace App\Domain\Calculation\Calculator;

use App\Domain\Calculation\CalculationStep;
use App\Domain\Calculation\CalculatorDefinition;
use App\Domain\Calculation\CalculatorInterface;
use App\Domain\Calculation\Computation;
use App\Domain\Calculation\OutputDefinition;
use App\Domain\Calculation\Rounding;
use App\Domain\Calculation\Unit;

final class OhmVoltage implements CalculatorInterface
{
    public function definition(): CalculatorDefinition
    {
        return new CalculatorDefinition(
            key: 'ohm_voltage',
            version: 1,
            title: 'Tension aux bornes d\'une résistance',
            description: 'Calcule la tension à partir de la résistance et du courant qui la traverse.',
            inputs: [Inputs::resistance(), Inputs::current()],
            output: new OutputDefinition('voltage', 'Tension', 'U', Unit::Volt, Rounding::Nearest, Inputs::MAX_VOLTAGE),
            assumptions: [Assumptions::RESISTIVE, Assumptions::CONSTANT_RESISTANCE],
            formula: 'U = R × I',
            source: 'Loi physique : loi d\'Ohm.',
        );
    }

    public function compute(array $inputs): Computation
    {
        $voltage = $inputs['resistance'] * $inputs['current'];

        return new Computation([new CalculationStep('Tension', 'U = R × I', $voltage, Unit::Volt->value)], $voltage);
    }
}
