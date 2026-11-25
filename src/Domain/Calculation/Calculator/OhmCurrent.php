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

final class OhmCurrent implements CalculatorInterface
{
    public function definition(): CalculatorDefinition
    {
        return new CalculatorDefinition(
            key: 'ohm_current',
            version: 1,
            title: 'Courant dans une résistance',
            description: 'Calcule le courant à partir de la tension et de la résistance.',
            inputs: [Inputs::voltage(), Inputs::resistance()],
            output: new OutputDefinition('current', 'Courant', 'I', Unit::Ampere, Rounding::Nearest, Inputs::MAX_CURRENT),
            assumptions: [Assumptions::RESISTIVE, Assumptions::CONSTANT_RESISTANCE],
            formula: 'I = U / R',
            source: 'Loi physique : loi d\'Ohm.',
        );
    }

    public function compute(array $inputs): Computation
    {
        $current = $inputs['voltage'] / $inputs['resistance'];

        return new Computation([new CalculationStep('Courant', 'I = U / R', $current, Unit::Ampere->value)], $current);
    }
}
