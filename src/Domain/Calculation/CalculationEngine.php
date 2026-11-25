<?php

declare(strict_types=1);

namespace App\Domain\Calculation;

use App\Domain\Calculation\Exception\CalculationRefusedException;

/**
 * Fait tourner un calculateur sur des entrées brutes : il valide tout avant de calculer, et
 * refuse en disant pourquoi plutôt que de supposer (CA2 à CA4). Aucune valeur par défaut :
 * une entrée absente est un refus.
 */
final class CalculationEngine
{
    /** En dessous, un résultat n'a plus de sens dans son unité : refusé plutôt qu'affiché comme zéro. */
    public const float SMALLEST_RESULT = 1.0e-9;

    /** Longueur gardée d'un texte reçu (clé ou unité inconnue) quand un refus le cite. */
    private const int QUOTED_LENGTH = 24;

    /**
     * @param array<mixed> $rawInputs par clé d'entrée : ['value' => nombre, 'unit' => symbole]
     *
     * @throws CalculationRefusedException
     */
    public static function run(CalculatorInterface $calculator, array $rawInputs): CalculationResult
    {
        $definition = $calculator->definition();
        $refusals = [];
        $given = [];
        $values = [];

        foreach (array_keys($rawInputs) as $key) {
            if ($definition->input((string) $key) === null) {
                $refusals[] = new Refusal((string) $key, 'Entrée inconnue pour ce calcul : %key%.', ['%key%' => self::quoted((string) $key)]);
            }
        }

        foreach ($definition->inputs as $input) {
            $raw = $rawInputs[$input->key] ?? null;
            $value = \is_array($raw) ? ($raw['value'] ?? null) : $raw;
            $symbol = \is_array($raw) && \is_string($raw['unit'] ?? null) ? trim($raw['unit']) : '';

            $refusal = self::check($input, $value, $symbol);
            if ($refusal !== null) {
                $refusals[] = $refusal;
                continue;
            }

            /** @var int|float $value */
            $quantity = new Quantity((float) $value, Unit::fromSymbol($symbol) ?? $input->unit);
            $given[$input->key] = $quantity;
            $values[$input->key] = $quantity->to($input->unit)->value;
        }

        if ($refusals !== []) {
            throw new CalculationRefusedException($refusals);
        }

        $computation = $calculator->compute($values);
        $output = $definition->output;
        if (!is_finite($computation->value) || $computation->value > $output->max) {
            throw new CalculationRefusedException([new Refusal(
                Refusal::RESULT,
                'Le résultat dépasse %bound%%unit%, limite de ce calcul : vérifiez les valeurs saisies.',
                ['%bound%' => $output->max, '%unit%' => self::suffix($output->unit)],
            )]);
        }
        if ($computation->value < self::SMALLEST_RESULT) {
            throw new CalculationRefusedException([new Refusal(
                Refusal::RESULT,
                'Le résultat est trop petit pour être exprimé en %unit% : vérifiez les valeurs saisies.',
                ['%unit%' => $output->unit->value],
            )]);
        }

        return new CalculationResult(
            $definition,
            $given,
            $computation->steps,
            new Quantity($output->rounding->apply($computation->value), $output->unit),
            $computation->value,
        );
    }

    /** Motif de refus d'une entrée, ou null si elle est recevable. */
    private static function check(InputDefinition $input, mixed $value, string $symbol): ?Refusal
    {
        $refuse = static fn (string $template, array $parameters = []): Refusal => new Refusal(
            $input->key,
            $template,
            ['%symbol%' => $input->symbol, ...$parameters],
            $input->label,
        );

        if ($value === null || $value === '') {
            return $refuse('%input% (%symbol%) : valeur manquante.');
        }
        // Un nombre, pas un texte : « 230 V » ou « environ 16 » ne sont pas des valeurs
        if ((!\is_int($value) && !\is_float($value)) || !is_finite((float) $value)) {
            return $refuse('%input% (%symbol%) : la valeur doit être un nombre.');
        }

        $accepted = implode(', ', array_map(static fn (Unit $unit): string => $unit->value, Unit::of($input->dimension())));
        if ($input->dimension() === Dimension::Ratio) {
            if ($symbol !== '') {
                return $refuse('%input% (%symbol%) : c\'est un nombre sans unité.');
            }
        } elseif ($symbol === '') {
            return $refuse('%input% (%symbol%) : indiquez l\'unité (%units%).', ['%units%' => $accepted]);
        } elseif (Unit::fromSymbol($symbol)?->dimension() !== $input->dimension()) {
            return $refuse('%input% (%symbol%) : l\'unité « %unit% » ne convient pas (%units%).', ['%unit%' => self::quoted($symbol), '%units%' => $accepted]);
        }

        $converted = (new Quantity((float) $value, Unit::fromSymbol($symbol) ?? $input->unit))->to($input->unit)->value;
        if ($converted <= $input->above) {
            return $refuse('%input% (%symbol%) : la valeur doit être supérieure à %bound%%unit%.', ['%bound%' => $input->above, '%unit%' => self::suffix($input->unit)]);
        }
        if ($converted > $input->max) {
            return $refuse('%input% (%symbol%) : la valeur ne doit pas dépasser %bound%%unit%, limite de ce calcul.', ['%bound%' => $input->max, '%unit%' => self::suffix($input->unit)]);
        }

        return null;
    }

    /** Unité écrite après un nombre : précédée d'une espace insécable, ou rien pour un rapport. */
    private static function suffix(Unit $unit): string
    {
        return $unit === Unit::None ? '' : "\u{00A0}" . $unit->value;
    }

    private static function quoted(string $text): string
    {
        return mb_strlen($text) > self::QUOTED_LENGTH ? mb_substr($text, 0, self::QUOTED_LENGTH) . '…' : $text;
    }
}
