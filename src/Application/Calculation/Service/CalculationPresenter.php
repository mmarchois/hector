<?php

declare(strict_types=1);

namespace App\Application\Calculation\Service;

use App\Application\Calculation\Port\CalculationTextsInterface;
use App\Domain\Calculation\CalculationResult;
use App\Domain\Calculation\CalculationStep;
use App\Domain\Calculation\CalculatorDefinition;
use App\Domain\Calculation\InputDefinition;
use App\Domain\Calculation\Rounding;
use App\Domain\Calculation\Unit;

/**
 * Ce qu'on montre d'un calculateur et d'un calcul, dans la langue du lecteur. Une seule
 * mise en forme pour les routes de calcul et pour le bloc de calcul d'une conversation :
 * l'application lit partout la même chose.
 */
final readonly class CalculationPresenter
{
    /** Chiffres significatifs de la valeur exacte quand elle est écrite en clair. */
    private const int EXACT_DIGITS = 6;

    public function __construct(
        private CalculationTextsInterface $texts,
    ) {
    }

    /**
     * @return array{key: string, version: int, title: string, description: string, inputs: list<array<string, mixed>>, output: array<string, mixed>, assumptions: list<string>, formula: string, source: string}
     */
    public function calculator(CalculatorDefinition $definition, ?string $locale = null): array
    {
        $output = $definition->output;

        return [
            'key' => $definition->key,
            'version' => $definition->version,
            'title' => $this->texts->text($definition->title, [], $locale),
            'description' => $this->texts->text($definition->description, [], $locale),
            'inputs' => array_map(fn (InputDefinition $input): array => [
                'key' => $input->key,
                'label' => $this->texts->text($input->label, [], $locale),
                'symbol' => $input->symbol,
                'unit' => $input->unit->value,
                'units' => array_map(static fn (Unit $unit): string => $unit->value, Unit::of($input->dimension())),
                'above' => $input->above,
                'max' => $input->max,
            ], $definition->inputs),
            'output' => [
                'key' => $output->key,
                'label' => $this->texts->text($output->label, [], $locale),
                'symbol' => $output->symbol,
                'unit' => $output->unit->value,
                'rounding' => $output->rounding->value,
                'roundingRule' => $this->texts->text($output->rounding->rule(), [], $locale),
                'max' => $output->max,
            ],
            'assumptions' => array_map(fn (string $assumption): string => $this->texts->text($assumption, [], $locale), $definition->assumptions),
            'formula' => $definition->formula,
            'source' => $this->texts->text($definition->source, [], $locale),
        ];
    }

    /**
     * Un calcul tel qu'il a été fait : entrées telles que données, hypothèses, étapes,
     * résultat arrondi et valeur exacte, version du calculateur.
     *
     * @return array{calculator: array{key: string, version: int, title: string}, formula: string, source: string, assumptions: list<string>, inputs: list<array{key: string, label: string, symbol: string, value: float, unit: string}>, steps: list<array{label: string, expression: string, value: float, unit: string}>, result: array{key: string, label: string, symbol: string, value: float, unit: string, exact: float, roundingRule: string}}
     */
    public function calculation(CalculationResult $result, ?string $locale = null): array
    {
        $definition = $result->definition;

        $inputs = [];
        foreach ($definition->inputs as $input) {
            $quantity = $result->given[$input->key];
            $inputs[] = [
                'key' => $input->key,
                'label' => $this->texts->text($input->label, [], $locale),
                'symbol' => $input->symbol,
                'value' => $quantity->value,
                'unit' => $quantity->unit->value,
            ];
        }

        return [
            'calculator' => ['key' => $definition->key, 'version' => $definition->version, 'title' => $this->texts->text($definition->title, [], $locale)],
            'formula' => $definition->formula,
            'source' => $this->texts->text($definition->source, [], $locale),
            'assumptions' => array_map(fn (string $assumption): string => $this->texts->text($assumption, [], $locale), $definition->assumptions),
            'inputs' => $inputs,
            // Une étape s'affiche arrondie au plus proche ; le calcul, lui, se poursuit sur sa valeur exacte
            'steps' => array_map(fn (CalculationStep $step): array => [
                'label' => $this->texts->text($step->label, [], $locale),
                'expression' => $step->expression,
                'value' => Rounding::Nearest->apply($step->value),
                'unit' => $step->unit,
            ], $result->steps),
            'result' => [
                'key' => $definition->output->key,
                'label' => $this->texts->text($definition->output->label, [], $locale),
                'symbol' => $definition->output->symbol,
                'value' => $result->result->value,
                'unit' => $result->result->unit->value,
                'exact' => $result->exact,
                'roundingRule' => $this->texts->text($definition->output->rounding->rule(), [], $locale),
            ],
        ];
    }

    /**
     * Le même calcul en texte seul : ce que lit une application qui ne connaît pas le bloc de
     * calcul, et ce que le modèle relit dans la suite de la conversation.
     */
    public function plainText(CalculationResult $result, ?string $locale = null): string
    {
        $view = $this->calculation($result, $locale);
        $text = fn (string $french, array $parameters = []): string => $this->texts->text($french, $parameters, $locale);

        $lines = [
            $text('Calcul : %title% (version %version%)', ['%title%' => $view['calculator']['title'], '%version%' => (string) $view['calculator']['version']]),
            $view['formula'],
        ];
        foreach ($view['inputs'] as $input) {
            $lines[] = $text('%label% (%symbol%) : %value%', ['%label%' => $input['label'], '%symbol%' => $input['symbol'], '%value%' => $this->quantity($input['value'], $input['unit'], $locale)]);
        }
        foreach ($view['steps'] as $step) {
            $lines[] = $text('%label% : %expression% = %value%', ['%label%' => $step['label'], '%expression%' => $step['expression'], '%value%' => $this->quantity($step['value'], $step['unit'], $locale)]);
        }
        $lines[] = $text('Résultat : %symbol% = %value%', ['%symbol%' => $view['result']['symbol'], '%value%' => $this->quantity($view['result']['value'], $view['result']['unit'], $locale)]);
        $exact = self::significant($view['result']['exact'], self::EXACT_DIGITS);
        if ($exact !== $view['result']['value']) {
            $lines[] = $text('Valeur exacte : %value%', ['%value%' => $this->quantity($exact, $view['result']['unit'], $locale)]);
        }
        $lines[] = $view['result']['roundingRule'];
        $lines[] = $text('Hypothèses :');
        foreach ($view['assumptions'] as $assumption) {
            $lines[] = '- ' . $assumption;
        }
        $lines[] = $text('Source : %source%', ['%source%' => $view['source']]);

        return implode("\n", $lines);
    }

    /** Un nombre et son unité, séparés par une espace insécable ; le nombre seul pour un rapport. */
    public function quantity(float $value, string $unit, ?string $locale = null): string
    {
        $number = $this->texts->number($value, $locale);

        return $unit === '' ? $number : $number . "\u{00A0}" . $unit;
    }

    private static function significant(float $value, int $digits): float
    {
        if ($value === 0.0 || !is_finite($value)) {
            return $value;
        }

        return round($value, max(0, $digits - 1 - (int) floor(log10(abs($value)))));
    }
}
