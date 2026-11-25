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

final class SinglePhasePower implements CalculatorInterface
{
    public function definition(): CalculatorDefinition
    {
        return new CalculatorDefinition(
            key: 'single_phase_power',
            version: 1,
            title: 'Puissance active en monophasé',
            description: 'Calcule la puissance active absorbée à partir de la tension, du courant et du facteur de puissance.',
            inputs: [Inputs::voltage(), Inputs::current(), Inputs::powerFactor()],
            output: new OutputDefinition('power', 'Puissance active', 'P', Unit::Watt, Rounding::Nearest, Inputs::MAX_POWER),
            assumptions: [Assumptions::SINUSOIDAL, Assumptions::POWER_FACTOR_GIVEN],
            formula: 'P = U × I × cos φ',
            source: 'Loi physique : puissance active en régime sinusoïdal monophasé.',
        );
    }

    public function compute(array $inputs): Computation
    {
        $apparent = $inputs['voltage'] * $inputs['current'];
        $active = $apparent * $inputs['powerFactor'];

        return new Computation([
            new CalculationStep('Puissance apparente', 'S = U × I', $apparent, 'VA'),
            new CalculationStep('Puissance active', 'P = S × cos φ', $active, Unit::Watt->value),
        ], $active);
    }
}
