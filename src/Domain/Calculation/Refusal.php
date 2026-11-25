<?php

declare(strict_types=1);

namespace App\Domain\Calculation;

/**
 * Motif de refus d'un calcul, rattaché à l'entrée en cause. Le texte est un gabarit français
 * et ses paramètres : il se traduit et s'écrit dans une autre langue sans que le domaine la
 * connaisse.
 */
final readonly class Refusal
{
    /** Le refus porte sur le résultat, pas sur une entrée. */
    public const string RESULT = 'result';

    /**
     * @param array<string, string|float> $parameters valeurs des %marqueurs% du gabarit ; un
     *                                                nombre s'écrit selon la langue du lecteur
     */
    public function __construct(
        /** Clé de l'entrée en cause, ou RESULT */
        public string $input,
        public string $template,
        public array $parameters = [],
        /** Nom français de la grandeur, qui remplace %input% une fois traduit */
        public ?string $label = null,
    ) {
    }

    /** Le refus, en français. */
    public function message(): string
    {
        $parameters = array_map(
            static fn (string|float $value): string => \is_float($value) ? self::number($value) : $value,
            $this->parameters,
        );
        if ($this->label !== null) {
            $parameters['%input%'] = $this->label;
        }

        return strtr($this->template, $parameters);
    }

    /** Écriture française d'un nombre : virgule décimale, milliers séparés, sans zéro inutile. */
    private static function number(float $value): string
    {
        $text = number_format($value, 6, ',', "\u{202F}");

        return str_contains($text, ',') ? rtrim(rtrim($text, '0'), ',') : $text;
    }
}
