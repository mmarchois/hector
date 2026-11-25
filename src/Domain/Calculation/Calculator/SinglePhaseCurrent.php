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

final class SinglePhaseCurrent implements CalculatorInterface
{
    public function definition(): CalculatorDefinition
    {
        return new CalculatorDefinition(
            key: 'single_phase_current',
            version: 1,
            title: 'Courant absorbé en monophasé',
            description: 'Calcule le courant absorbé par un récepteur à partir de sa puissance active, de la tension et du facteur de puissance.',
            inputs: [Inputs::power(), Inputs::voltage(), Inputs::powerFactor()],
            output: new OutputDefinition('current', 'Courant absorbé', 'I', Unit::Ampere, Rounding::Up, Inputs::MAX_CURRENT),
            assumptions: [Assumptions::SINUSOIDAL, Assumptions::POWER_FACTOR_GIVEN, Assumptions::NO_COEFFICIENT],
            formula: 'I = P / (U × cos φ)',
            source: 'Loi physique : puissance active en régime sinusoïdal monophasé.',
        );
    }

    public function compute(array $inputs): Computation
    {
        $apparent = $inputs['power'] / $inputs['powerFactor'];
        $current = $apparent / $inputs['voltage'];

        return new Computation([
            new CalculationStep('Puissance apparente', 'S = P / cos φ', $apparent, 'VA'),
            new CalculationStep('Courant absorbé', 'I = S / U', $current, Unit::Ampere->value),
        ], $current);
    }
}
