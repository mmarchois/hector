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

final class OhmResistance implements CalculatorInterface
{
    public function definition(): CalculatorDefinition
    {
        return new CalculatorDefinition(
            key: 'ohm_resistance',
            version: 1,
            title: 'Résistance d\'un récepteur',
            description: 'Calcule la résistance à partir de la tension et du courant.',
            inputs: [Inputs::voltage(), Inputs::current()],
            output: new OutputDefinition('resistance', 'Résistance', 'R', Unit::Ohm, Rounding::Nearest, Inputs::MAX_RESISTANCE),
            assumptions: [Assumptions::RESISTIVE, Assumptions::CONSTANT_RESISTANCE],
            formula: 'R = U / I',
            source: 'Loi physique : loi d\'Ohm.',
        );
    }

    public function compute(array $inputs): Computation
    {
        $resistance = $inputs['voltage'] / $inputs['current'];

        return new Computation([new CalculationStep('Résistance', 'R = U / I', $resistance, Unit::Ohm->value)], $resistance);
    }
}
