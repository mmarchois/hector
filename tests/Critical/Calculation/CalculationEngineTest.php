<?php

declare(strict_types=1);

namespace App\Tests\Critical\Calculation;

use App\Domain\Calculation\CalculationEngine;
use App\Domain\Calculation\CalculationResult;
use App\Domain\Calculation\CalculationStep;
use App\Domain\Calculation\Calculator\Assumptions;
use App\Domain\Calculation\CalculatorCatalog;
use App\Domain\Calculation\CalculatorInterface;
use App\Domain\Calculation\Exception\CalculationRefusedException;
use App\Domain\Calculation\Exception\CalculatorNotFoundException;
use App\Domain\Calculation\InputDefinition;
use App\Domain\Calculation\Quantity;
use App\Domain\Calculation\Unit;
use PHPUnit\Framework\TestCase;

/**
 * Règles du calcul (docs/DOMAIN.md, § 4.4), une par une : CA1 à CA7. Un calcul est exact et
 * expliqué, ou refusé avec son motif ; jamais complété par une valeur supposée.
 */
final class CalculationEngineTest extends TestCase
{
    private const array VOLTS = ['value' => 230, 'unit' => 'V'];
    private const array AMPS = ['value' => 16, 'unit' => 'A'];
    private const array OHMS = ['value' => 23, 'unit' => 'Ω'];

    public function testCa1TheSameInputsGiveTheSameResult(): void
    {
        $inputs = ['power' => ['value' => 3, 'unit' => 'kW'], 'voltage' => self::VOLTS, 'powerFactor' => ['value' => 0.9]];

        self::assertEquals(
            $this->calculate('single_phase_current', $inputs),
            $this->calculate('single_phase_current', $inputs),
        );
    }

    public function testCa1ACalculatorCannotReceiveAnyDependency(): void
    {
        // Sans constructeur, un calculateur ne peut recevoir ni base de données, ni horloge,
        // ni modèle de langage : il ne dépend que de ses entrées
        foreach ((new CalculatorCatalog())->all() as $calculator) {
            self::assertNull((new \ReflectionClass($calculator))->getConstructor(), $calculator::class);
        }
    }

    public function testAResultComesWithItsInputsItsStepsItsAssumptionsItsFormulaAndItsSource(): void
    {
        $result = $this->calculate('single_phase_current', [
            'power' => ['value' => 3, 'unit' => 'kW'],
            'voltage' => self::VOLTS,
            'powerFactor' => ['value' => 0.9],
        ]);

        // 3 000 / (230 × 0,9) = 14,4927… ; un courant à prévoir s'arrondit par excès
        self::assertEquals(new Quantity(14.5, Unit::Ampere), $result->result);
        self::assertEqualsWithDelta(14.492753623188406, $result->exact, 1e-12);

        // Les entrées restent telles qu'elles ont été données
        self::assertEquals(new Quantity(3.0, Unit::Kilowatt), $result->given['power']);
        self::assertEquals(new Quantity(230.0, Unit::Volt), $result->given['voltage']);
        self::assertEquals(new Quantity(0.9, Unit::None), $result->given['powerFactor']);

        self::assertSame(['S = P / cos φ', 'I = S / U'], array_map(static fn (CalculationStep $step): string => $step->expression, $result->steps));
        self::assertSame(['Puissance apparente', 'Courant absorbé'], array_map(static fn (CalculationStep $step): string => $step->label, $result->steps));
        self::assertEqualsWithDelta(3333.3333333333335, $result->steps[0]->value, 1e-9);
        self::assertSame('VA', $result->steps[0]->unit);
        self::assertSame($result->exact, $result->steps[1]->value);
        self::assertSame('A', $result->steps[1]->unit);

        $definition = $result->definition;
        self::assertSame('single_phase_current', $definition->key);
        self::assertSame(1, $definition->version);
        self::assertSame('I = P / (U × cos φ)', $definition->formula);
        self::assertSame('Loi physique : puissance active en régime sinusoïdal monophasé.', $definition->source);
        self::assertSame([Assumptions::SINUSOIDAL, Assumptions::POWER_FACTOR_GIVEN, Assumptions::NO_COEFFICIENT], $definition->assumptions);
    }

    public function testCa2ANumberWithoutUnitIsRefused(): void
    {
        self::assertSame(
            [
                'resistance' => 'Résistance (R) : indiquez l\'unité (Ω, mΩ, kΩ, MΩ).',
                'current' => 'Courant (I) : indiquez l\'unité (A, mA, kA).',
            ],
            $this->refusals('ohm_voltage', ['resistance' => ['value' => 23], 'current' => 10]),
        );
        self::assertSame(
            ['voltage' => 'Tension (U) : indiquez l\'unité (V, mV, kV).'],
            $this->refusals('ohm_current', ['voltage' => ['value' => 230, 'unit' => '  '], 'resistance' => self::OHMS]),
        );
    }

    public function testCa2AUnitOfAnotherQuantityOrAnUnknownUnitIsRefused(): void
    {
        self::assertSame(
            ['resistance' => 'Résistance (R) : l\'unité « V » ne convient pas (Ω, mΩ, kΩ, MΩ).'],
            $this->refusals('ohm_voltage', ['resistance' => ['value' => 23, 'unit' => 'V'], 'current' => self::AMPS]),
        );
        self::assertSame(
            ['voltage' => 'Tension (U) : l\'unité « volts » ne convient pas (V, mV, kV).'],
            $this->refusals('ohm_current', ['voltage' => ['value' => 230, 'unit' => 'volts'], 'resistance' => self::OHMS]),
        );
        // La casse compte : MV (mégavolt) n'est pas mV, et « v » n'est pas une unité
        self::assertArrayHasKey('voltage', $this->refusals('ohm_current', ['voltage' => ['value' => 230, 'unit' => 'v'], 'resistance' => self::OHMS]));
        self::assertArrayHasKey('voltage', $this->refusals('ohm_current', ['voltage' => ['value' => 230, 'unit' => 'MV'], 'resistance' => self::OHMS]));
        // Une unité inconnue très longue est citée tronquée
        self::assertSame(
            ['voltage' => 'Tension (U) : l\'unité « ' . str_repeat('x', 24) . '… » ne convient pas (V, mV, kV).'],
            $this->refusals('ohm_current', ['voltage' => ['value' => 230, 'unit' => str_repeat('x', 200)], 'resistance' => self::OHMS]),
        );
    }

    public function testCa2TheSameQuantityInAnotherUnitGivesTheSameResult(): void
    {
        $base = $this->calculate('ohm_voltage', ['resistance' => ['value' => 500, 'unit' => 'Ω'], 'current' => ['value' => 0.02, 'unit' => 'A']]);
        $other = $this->calculate('ohm_voltage', ['resistance' => ['value' => 0.5, 'unit' => 'kΩ'], 'current' => ['value' => 20, 'unit' => 'mA']]);

        self::assertSame(10.0, $base->result->value);
        self::assertSame(10.0, $other->result->value);
        self::assertEquals(new Quantity(0.5, Unit::Kiloohm), $other->given['resistance']);
        self::assertEquals(new Quantity(20.0, Unit::Milliampere), $other->given['current']);

        // « ohm » écrit en lettres, ou avec le signe ohm (U+2126) au lieu de la lettre oméga
        foreach (['ohm', "\u{2126}", ' Ω '] as $symbol) {
            self::assertSame(230.0, $this->calculate('ohm_voltage', ['resistance' => ['value' => 23, 'unit' => $symbol], 'current' => ['value' => 10, 'unit' => 'A']])->result->value);
        }
    }

    public function testCa2ARatioIsGivenWithoutUnit(): void
    {
        foreach ([0.9, ['value' => 0.9], ['value' => 0.9, 'unit' => ''], ['value' => 0.9, 'unit' => null]] as $powerFactor) {
            self::assertSame(3312.0, $this->calculate('single_phase_power', ['voltage' => self::VOLTS, 'current' => self::AMPS, 'powerFactor' => $powerFactor])->result->value);
        }

        self::assertSame(
            ['powerFactor' => 'Facteur de puissance (cos φ) : c\'est un nombre sans unité.'],
            $this->refusals('single_phase_power', ['voltage' => self::VOLTS, 'current' => self::AMPS, 'powerFactor' => ['value' => 90, 'unit' => '%']]),
        );
    }

    public function testCa3AValueOutOfItsBoundsIsRefusedWithTheReason(): void
    {
        $voltage = fn (int|float $value, string $unit): array => $this->refusals('ohm_current', ['voltage' => ['value' => $value, 'unit' => $unit], 'resistance' => self::OHMS]);
        $powerFactor = fn (int|float $value): array => $this->refusals('single_phase_power', ['voltage' => self::VOLTS, 'current' => self::AMPS, 'powerFactor' => $value]);

        self::assertSame(['voltage' => "Tension (U) : la valeur doit être supérieure à 0\u{00A0}V."], $voltage(0, 'V'));
        self::assertSame(['voltage' => "Tension (U) : la valeur doit être supérieure à 0\u{00A0}V."], $voltage(-230, 'V'));
        // La borne se compare dans l'unité de base : 20 kV, c'est 20 000 V
        self::assertSame(['voltage' => "Tension (U) : la valeur ne doit pas dépasser 1\u{202F}000\u{00A0}V, limite de ce calcul."], $voltage(20, 'kV'));
        self::assertSame(['voltage' => "Tension (U) : la valeur ne doit pas dépasser 1\u{202F}000\u{00A0}V, limite de ce calcul."], $voltage(1000.5, 'V'));
        self::assertSame(['powerFactor' => 'Facteur de puissance (cos φ) : la valeur doit être supérieure à 0.'], $powerFactor(0));
        self::assertSame(['powerFactor' => 'Facteur de puissance (cos φ) : la valeur ne doit pas dépasser 1, limite de ce calcul.'], $powerFactor(1.01));
    }

    public function testCa3TheUpperBoundItselfIsAccepted(): void
    {
        self::assertSame(1000.0, $this->calculate('ohm_current', ['voltage' => ['value' => 1, 'unit' => 'kV'], 'resistance' => ['value' => 1, 'unit' => 'Ω']])->result->value);
        self::assertSame(3680.0, $this->calculate('single_phase_power', ['voltage' => self::VOLTS, 'current' => self::AMPS, 'powerFactor' => 1])->result->value);
    }

    public function testCa3AResultOutOfTheCoveredDomainIsRefused(): void
    {
        self::assertSame(
            ['result' => "Le résultat dépasse 1\u{202F}000\u{00A0}V, limite de ce calcul : vérifiez les valeurs saisies."],
            $this->refusals('ohm_voltage', ['resistance' => ['value' => 1, 'unit' => 'MΩ'], 'current' => ['value' => 10, 'unit' => 'A']]),
        );
        // 1 mV sur 1 000 MΩ : un millième de milliardième d'ampère, que « 0 A » trahirait
        self::assertSame(
            ['result' => 'Le résultat est trop petit pour être exprimé en A : vérifiez les valeurs saisies.'],
            $this->refusals('ohm_current', ['voltage' => ['value' => 1, 'unit' => 'mV'], 'resistance' => ['value' => 1000, 'unit' => 'MΩ']]),
        );
    }

    public function testCa4AMissingInputIsRefusedNeverAssumed(): void
    {
        $missing = ['powerFactor' => 'Facteur de puissance (cos φ) : valeur manquante.'];
        $given = ['voltage' => self::VOLTS, 'current' => self::AMPS];

        self::assertSame($missing, $this->refusals('single_phase_power', $given));
        self::assertSame($missing, $this->refusals('single_phase_power', [...$given, 'powerFactor' => null]));
        self::assertSame($missing, $this->refusals('single_phase_power', [...$given, 'powerFactor' => ['value' => null]]));
        self::assertSame($missing, $this->refusals('single_phase_power', [...$given, 'powerFactor' => ['value' => '']]));
        self::assertSame($missing, $this->refusals('single_phase_power', [...$given, 'powerFactor' => ['unit' => '']]));

        self::assertSame(
            [
                'voltage' => 'Tension entre phases (U) : valeur manquante.',
                'current' => 'Courant de ligne (I) : valeur manquante.',
                'powerFactor' => 'Facteur de puissance (cos φ) : valeur manquante.',
            ],
            $this->refusals('three_phase_power', []),
        );
    }

    public function testCa4EveryCalculatorStatesItsAssumptions(): void
    {
        foreach ((new CalculatorCatalog())->all() as $calculator) {
            $definition = $calculator->definition();
            self::assertNotSame([], $definition->assumptions, $definition->key);
            // Un facteur de puissance demandé n'est jamais supposé : c'est dit
            if ($definition->input('powerFactor') !== null) {
                self::assertContains(Assumptions::POWER_FACTOR_GIVEN, $definition->assumptions, $definition->key);
            }
        }
    }

    public function testAValueThatIsNotANumberIsRefused(): void
    {
        foreach (['230', '230 V', 'environ 230', true, false, [], \INF, \NAN] as $value) {
            self::assertSame(
                ['voltage' => 'Tension (U) : la valeur doit être un nombre.'],
                $this->refusals('ohm_current', ['voltage' => ['value' => $value, 'unit' => 'V'], 'resistance' => self::OHMS]),
                var_export($value, true),
            );
        }
    }

    public function testAnUnknownInputIsRefusedNotIgnored(): void
    {
        self::assertSame(
            ['frequency' => 'Entrée inconnue pour ce calcul : frequency.'],
            $this->refusals('ohm_voltage', ['resistance' => self::OHMS, 'current' => self::AMPS, 'frequency' => ['value' => 50, 'unit' => 'Hz']]),
        );
        self::assertSame(
            ['0' => 'Entrée inconnue pour ce calcul : 0.', 'resistance' => 'Résistance (R) : valeur manquante.', 'current' => 'Courant (I) : valeur manquante.'],
            $this->refusals('ohm_voltage', [230]),
        );
    }

    public function testAllTheReasonsAreGivenAtOnce(): void
    {
        try {
            $this->calculate('ohm_voltage', ['resistance' => ['value' => 23]]);
            self::fail('Le calcul aurait dû être refusé.');
        } catch (CalculationRefusedException $e) {
            self::assertCount(2, $e->refusals);
            self::assertSame('Résistance (R) : indiquez l\'unité (Ω, mΩ, kΩ, MΩ). Courant (I) : valeur manquante.', $e->getMessage());
            self::assertSame('Résistance', $e->refusals[0]->label);
            self::assertSame(['%symbol%' => 'R', '%units%' => 'Ω, mΩ, kΩ, MΩ'], $e->refusals[0]->parameters);
        }
    }

    public function testCa6TheResultIsRoundedByTheStatedRuleAndTheExactValueIsKept(): void
    {
        // Au plus proche : 230 / 16 = 14,375
        $resistance = $this->calculate('ohm_resistance', ['voltage' => self::VOLTS, 'current' => self::AMPS]);
        self::assertSame(14.38, $resistance->result->value);
        self::assertSame(14.375, $resistance->exact);

        // Par excès, côté sûr pour un courant à prévoir : 3 000 / 184 = 16,3043…
        $current = $this->calculate('single_phase_current', ['power' => ['value' => 3000, 'unit' => 'W'], 'voltage' => self::VOLTS, 'powerFactor' => 0.8]);
        self::assertSame(16.31, $current->result->value);
        self::assertEqualsWithDelta(16.304347826086957, $current->exact, 1e-12);

        // Une valeur déjà ronde ne monte pas d'un cran : 3 680 / 230 = 16
        self::assertSame(16.0, $this->calculate('single_phase_current', ['power' => ['value' => 3680, 'unit' => 'W'], 'voltage' => self::VOLTS, 'powerFactor' => 1])->result->value);
    }

    public function testCa7EveryCalculatorStatesItsVersionItsInputsItsFormulaAndItsSource(): void
    {
        $calculators = (new CalculatorCatalog())->all();

        self::assertSame(
            ['ohm_voltage', 'ohm_current', 'ohm_resistance', 'single_phase_power', 'three_phase_power', 'single_phase_current', 'three_phase_current'],
            array_map(static fn (CalculatorInterface $calculator): string => $calculator->definition()->key, $calculators),
        );

        foreach ($calculators as $calculator) {
            $definition = $calculator->definition();
            $keys = array_map(static fn (InputDefinition $input): string => $input->key, $definition->inputs);

            self::assertGreaterThanOrEqual(1, $definition->version, $definition->key);
            self::assertNotSame('', $definition->title, $definition->key);
            self::assertNotSame('', $definition->description, $definition->key);
            self::assertNotSame([], $keys, $definition->key);
            self::assertSame($keys, array_unique($keys), $definition->key);
            // La formule donne le résultat, et sa source est une loi physique tant qu'aucun
            // registre normatif n'existe (lot L11)
            self::assertStringStartsWith($definition->output->symbol . ' = ', $definition->formula, $definition->key);
            self::assertStringStartsWith('Loi physique : ', $definition->source, $definition->key);
            foreach ($definition->inputs as $input) {
                self::assertLessThan($input->max, $input->above, $definition->key . '.' . $input->key);
                self::assertStringContainsString($input->symbol, $definition->formula, $definition->key . '.' . $input->key);
                self::assertSame($input, $definition->input($input->key));
            }
            self::assertNull($definition->input('inconnue'));
        }
    }

    public function testAnUnknownCalculatorDoesNotExist(): void
    {
        $this->expectException(CalculatorNotFoundException::class);

        (new CalculatorCatalog())->get('voltage_drop');
    }

    /**
     * @param array<mixed> $inputs
     */
    private function calculate(string $calculator, array $inputs): CalculationResult
    {
        return CalculationEngine::run((new CalculatorCatalog())->get($calculator), $inputs);
    }

    /**
     * @param array<mixed> $inputs
     *
     * @return array<string, string> entrée refusée => motif
     */
    private function refusals(string $calculator, array $inputs): array
    {
        try {
            $this->calculate($calculator, $inputs);
        } catch (CalculationRefusedException $e) {
            $reasons = [];
            foreach ($e->refusals as $refusal) {
                $reasons[$refusal->input] = $refusal->message();
            }

            return $reasons;
        }

        self::fail('Le calcul aurait dû être refusé.');
    }
}
