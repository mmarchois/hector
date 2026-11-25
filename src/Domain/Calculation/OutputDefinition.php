<?php

declare(strict_types=1);

namespace App\Domain\Calculation;

final readonly class OutputDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public string $symbol,
        public Unit $unit,
        /** Sens de l'arrondi, explicite (CA6) */
        public Rounding $rounding,
        /** Au-delà, le résultat sort du domaine couvert : le calcul est refusé (CA3) */
        public float $max,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'symbol' => $this->symbol, 'unit' => $this->unit->value, 'rounding' => $this->rounding->value, 'max' => $this->max];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['key'], $data['label'], $data['symbol'], Unit::from($data['unit']), Rounding::from($data['rounding']), (float) $data['max']);
    }
}
