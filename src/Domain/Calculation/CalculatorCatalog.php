<?php

declare(strict_types=1);

namespace App\Domain\Calculation;

use App\Domain\Calculation\Calculator\OhmCurrent;
use App\Domain\Calculation\Calculator\OhmResistance;
use App\Domain\Calculation\Calculator\OhmVoltage;
use App\Domain\Calculation\Calculator\SinglePhaseCurrent;
use App\Domain\Calculation\Calculator\SinglePhasePower;
use App\Domain\Calculation\Calculator\ThreePhaseCurrent;
use App\Domain\Calculation\Calculator\ThreePhasePower;
use App\Domain\Calculation\Exception\CalculatorNotFoundException;

/**
 * Les calculateurs proposés, dans l'ordre où on les présente. Un calculateur se construit sans
 * rien recevoir : il ne peut donc lire ni base de données, ni horloge, ni modèle de langage (CA1).
 */
final class CalculatorCatalog
{
    /** @var array<string, CalculatorInterface> */
    private array $calculators = [];

    public function __construct()
    {
        $calculators = [
            new OhmVoltage(),
            new OhmCurrent(),
            new OhmResistance(),
            new SinglePhasePower(),
            new ThreePhasePower(),
            new SinglePhaseCurrent(),
            new ThreePhaseCurrent(),
        ];
        foreach ($calculators as $calculator) {
            $this->calculators[$calculator->definition()->key] = $calculator;
        }
    }

    /** @return list<CalculatorInterface> */
    public function all(): array
    {
        return array_values($this->calculators);
    }

    /** @throws CalculatorNotFoundException */
    public function get(string $key): CalculatorInterface
    {
        return $this->calculators[$key] ?? throw new CalculatorNotFoundException();
    }
}
