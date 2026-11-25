<?php

declare(strict_types=1);

namespace App\Tests\Critical\Calculation;

use App\Domain\Calculation\CalculationEngine;
use App\Domain\Calculation\CalculatorCatalog;
use App\Domain\Calculation\CalculatorDefinition;
use App\Domain\Calculation\Quantity;
use App\Domain\Calculation\Rounding;
use App\Domain\Calculation\Unit;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Propriétés des calculateurs, vérifiées sur des tirages reproductibles : sens de variation,
 * cohérence d'un calcul avec son inverse, indifférence à l'unité choisie, arrondi.
 */
final class CalculatorPropertiesTest extends TestCase
{
    private const int DRAWS = 300;

    /** Sens dans lequel le résultat varie quand une entrée augmente, les autres restant fixes. */
    private const array DIRECTIONS = [
        'ohm_voltage' => ['resistance' => 1, 'current' => 1],
        'ohm_current' => ['voltage' => 1, 'resistance' => -1],
        'ohm_resistance' => ['voltage' => 1, 'current' => -1],
        'single_phase_power' => ['voltage' => 1, 'current' => 1, 'powerFactor' => 1],
        'three_phase_power' => ['voltage' => 1, 'current' => 1, 'powerFactor' => 1],
        'single_phase_current' => ['power' => 1, 'voltage' => -1, 'powerFactor' => -1],
        'three_phase_current' => ['power' => 1, 'voltage' => -1, 'powerFactor' => -1],
    ];

    /** Plages de tirage, dans l'unité de base : tout résultat y reste dans le domaine couvert. */
    private const array RANGES = [
        'voltage' => [100.0, 900.0],
        'current' => [0.01, 90.0],
        'resistance' => [0.1, 10.0],
        'power' => [1.0, 50_000.0],
        'powerFactor' => [0.1, 0.9],
    ];

    private CalculatorCatalog $catalog;
    private Randomizer $random;

    protected function setUp(): void
    {
        $this->catalog = new CalculatorCatalog();
        $this->random = new Randomizer(new Mt19937(13));
    }

    public function testEveryInputOfEveryCalculatorHasADeclaredDirection(): void
    {
        $declared = [];
        foreach ($this->catalog->all() as $calculator) {
            $definition = $calculator->definition();
            foreach ($definition->inputs as $input) {
                self::assertArrayHasKey($input->key, self::DIRECTIONS[$definition->key] ?? [], $definition->key . '.' . $input->key);
                self::assertArrayHasKey($input->key, self::RANGES);
            }
            $declared[] = $definition->key;
        }

        self::assertEqualsCanonicalizing(array_keys(self::DIRECTIONS), $declared);
    }

    public function testTheResultVariesInTheExpectedDirection(): void
    {
        foreach ($this->catalog->all() as $calculator) {
            $definition = $calculator->definition();
            for ($i = 0; $i < self::DRAWS; ++$i) {
                $inputs = $this->draw($definition);
                $before = $calculator->compute($inputs)->value;

                foreach (self::DIRECTIONS[$definition->key] as $key => $direction) {
                    $after = $calculator->compute([...$inputs, $key => $inputs[$key] * 1.1])->value;

                    self::assertGreaterThan(0.0, ($after - $before) * $direction, \sprintf('%s : %s augmente', $definition->key, $key));
                }
            }
        }
    }

    public function testACalculationAndItsReverseAgree(): void
    {
        $compute = fn (string $key, array $inputs): float => $this->catalog->get($key)->compute($inputs)->value;

        for ($i = 0; $i < self::DRAWS; ++$i) {
            $voltage = $this->random->getFloat(...self::RANGES['voltage']);
            $current = $this->random->getFloat(...self::RANGES['current']);
            $resistance = $this->random->getFloat(...self::RANGES['resistance']);
            $powerFactor = $this->random->getFloat(...self::RANGES['powerFactor']);

            // Loi d'Ohm, dans ses trois écritures
            $ohmCurrent = $compute('ohm_current', ['voltage' => $voltage, 'resistance' => $resistance]);
            self::assertEqualsWithDelta($voltage, $compute('ohm_voltage', ['resistance' => $resistance, 'current' => $ohmCurrent]), $voltage * 1e-12);
            self::assertEqualsWithDelta($resistance, $compute('ohm_resistance', ['voltage' => $voltage, 'current' => $ohmCurrent]), $resistance * 1e-12);

            // Le courant recalculé depuis la puissance est celui dont on est parti
            foreach (['single_phase', 'three_phase'] as $network) {
                $power = $compute($network . '_power', ['voltage' => $voltage, 'current' => $current, 'powerFactor' => $powerFactor]);

                self::assertEqualsWithDelta(
                    $current,
                    $compute($network . '_current', ['power' => $power, 'voltage' => $voltage, 'powerFactor' => $powerFactor]),
                    $current * 1e-12,
                    $network,
                );
            }

            // Triphasé équilibré : racine de trois fois le monophasé, à tension et courant égaux
            $inputs = ['voltage' => $voltage, 'current' => $current, 'powerFactor' => $powerFactor];
            self::assertEqualsWithDelta(
                sqrt(3.0) * $compute('single_phase_power', $inputs),
                $compute('three_phase_power', $inputs),
                $voltage * $current * 1e-12,
            );
        }
    }

    public function testTheUnitChosenForAnInputDoesNotChangeTheResult(): void
    {
        foreach ($this->catalog->all() as $calculator) {
            $definition = $calculator->definition();
            for ($i = 0; $i < self::DRAWS; ++$i) {
                $values = $this->draw($definition);

                $base = [];
                $other = [];
                foreach ($definition->inputs as $input) {
                    $units = Unit::of($input->dimension());
                    $unit = $units[$this->random->getInt(0, \count($units) - 1)];

                    $base[$input->key] = ['value' => $values[$input->key], 'unit' => $input->unit->value];
                    $other[$input->key] = ['value' => (new Quantity($values[$input->key], $input->unit))->to($unit)->value, 'unit' => $unit->value];
                }

                $expected = CalculationEngine::run($calculator, $base);
                $result = CalculationEngine::run($calculator, $other);

                self::assertEqualsWithDelta($expected->exact, $result->exact, $expected->exact * 1e-9, $definition->key);
                self::assertSame($definition->output->unit, $result->result->unit);
            }
        }
    }

    public function testTheResultShownIsTheExactValueRoundedByTheRuleOfTheCalculator(): void
    {
        foreach ($this->catalog->all() as $calculator) {
            $definition = $calculator->definition();
            $rounding = $definition->output->rounding;
            for ($i = 0; $i < self::DRAWS; ++$i) {
                $inputs = [];
                foreach ($this->draw($definition) as $key => $value) {
                    $inputs[$key] = ['value' => $value, 'unit' => $definition->input($key)?->unit->value];
                }
                $result = CalculationEngine::run($calculator, $inputs);
                $step = 10.0 ** -Rounding::decimalsFor($result->exact);

                self::assertSame($rounding->apply($result->exact), $result->result->value, $definition->key);
                self::assertEqualsWithDelta($result->exact, $result->result->value, $step * (1 + 1e-9), $definition->key);
                if ($rounding === Rounding::Up) {
                    self::assertGreaterThanOrEqual($result->exact * (1 - 1e-9), $result->result->value, $definition->key);
                }
                // La dernière étape donne le résultat, avant arrondi
                self::assertSame($result->exact, $result->steps[array_key_last($result->steps)]->value, $definition->key);
            }
        }
    }

    public function testTheCurrentDrawnIsRoundedUpAndNothingElse(): void
    {
        foreach ($this->catalog->all() as $calculator) {
            $definition = $calculator->definition();

            self::assertSame(
                \in_array($definition->key, ['single_phase_current', 'three_phase_current'], true) ? Rounding::Up : Rounding::Nearest,
                $definition->output->rounding,
                $definition->key,
            );
        }
    }

    /**
     * @return array<string, float> une valeur par entrée, dans son unité de base
     */
    private function draw(CalculatorDefinition $definition): array
    {
        $inputs = [];
        foreach ($definition->inputs as $input) {
            $inputs[$input->key] = $this->random->getFloat(...self::RANGES[$input->key]);
        }

        return $inputs;
    }
}
