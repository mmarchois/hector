<?php

declare(strict_types=1);

namespace App\Domain\Calculation;

/** Entrée d'un calculateur : sa grandeur, l'unité dans laquelle le calcul la prend, ses bornes admises (CA3). */
final readonly class InputDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public string $symbol,
        public Unit $unit,
        /** Borne basse, exclue : toutes les grandeurs traitées sont strictement positives */
        public float $above,
        /** Borne haute, incluse */
        public float $max,
    ) {
    }

    public function dimension(): Dimension
    {
        return $this->unit->dimension();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'symbol' => $this->symbol, 'unit' => $this->unit->value, 'above' => $this->above, 'max' => $this->max];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['key'], $data['label'], $data['symbol'], Unit::from($data['unit']), (float) $data['above'], (float) $data['max']);
    }
}
