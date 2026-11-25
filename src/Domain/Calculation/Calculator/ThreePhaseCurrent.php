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

final class ThreePhaseCurrent implements CalculatorInterface
{
    public function definition(): CalculatorDefinition
    {
        return new CalculatorDefinition(
            key: 'three_phase_current',
            version: 1,
            title: 'Courant absorbé en triphasé',
            description: 'Calcule le courant de ligne absorbé par un récepteur triphasé équilibré à partir de sa puissance active, de la tension entre phases et du facteur de puissance.',
            inputs: [Inputs::power(), Inputs::voltage('Tension entre phases'), Inputs::powerFactor()],
            output: new OutputDefinition('current', 'Courant de ligne absorbé', 'I', Unit::Ampere, Rounding::Up, Inputs::MAX_CURRENT),
            assumptions: [Assumptions::SINUSOIDAL, Assumptions::BALANCED, Assumptions::LINE_VOLTAGE, Assumptions::POWER_FACTOR_GIVEN, Assumptions::NO_COEFFICIENT],
            formula: 'I = P / (√3 × U × cos φ)',
            source: 'Loi physique : puissance active d\'un système triphasé équilibré en régime sinusoïdal.',
        );
    }

    public function compute(array $inputs): Computation
    {
        $apparent = $inputs['power'] / $inputs['powerFactor'];
        $current = $apparent / (sqrt(3.0) * $inputs['voltage']);

        return new Computation([
            new CalculationStep('Puissance apparente', 'S = P / cos φ', $apparent, 'VA'),
            new CalculationStep('Courant de ligne absorbé', 'I = S / (√3 × U)', $current, Unit::Ampere->value),
        ], $current);
    }
}
