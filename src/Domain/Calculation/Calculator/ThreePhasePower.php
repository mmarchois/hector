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

final class ThreePhasePower implements CalculatorInterface
{
    public function definition(): CalculatorDefinition
    {
        return new CalculatorDefinition(
            key: 'three_phase_power',
            version: 1,
            title: 'Puissance active en triphasé',
            description: 'Calcule la puissance active absorbée en triphasé équilibré à partir de la tension entre phases, du courant de ligne et du facteur de puissance.',
            inputs: [Inputs::voltage('Tension entre phases'), Inputs::current('Courant de ligne'), Inputs::powerFactor()],
            output: new OutputDefinition('power', 'Puissance active', 'P', Unit::Watt, Rounding::Nearest, Inputs::MAX_POWER),
            assumptions: [Assumptions::SINUSOIDAL, Assumptions::BALANCED, Assumptions::LINE_VOLTAGE, Assumptions::POWER_FACTOR_GIVEN],
            formula: 'P = √3 × U × I × cos φ',
            source: 'Loi physique : puissance active d\'un système triphasé équilibré en régime sinusoïdal.',
        );
    }

    public function compute(array $inputs): Computation
    {
        $apparent = sqrt(3.0) * $inputs['voltage'] * $inputs['current'];
        $active = $apparent * $inputs['powerFactor'];

        return new Computation([
            new CalculationStep('Puissance apparente', 'S = √3 × U × I', $apparent, 'VA'),
            new CalculationStep('Puissance active', 'P = S × cos φ', $active, Unit::Watt->value),
        ], $active);
    }
}
