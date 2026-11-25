<?php

declare(strict_types=1);

namespace App\Domain\Calculation;

/** Étape d'un calcul : ce qu'on calcule, comment, et ce qu'on trouve. */
final readonly class CalculationStep
{
    public function __construct(
        public string $label,
        /** Expression, écrite avec les symboles des entrées */
        public string $expression,
        /** Valeur exacte : le calcul se poursuit sur elle, l'affichage l'arrondit */
        public float $value,
        /** Unité affichée ; une étape peut porter une unité que les entrées n'ont pas (VA) */
        public string $unit,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['label' => $this->label, 'expression' => $this->expression, 'value' => $this->value, 'unit' => $this->unit];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['label'], $data['expression'], (float) $data['value'], $data['unit']);
    }
}
