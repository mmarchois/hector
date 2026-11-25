<?php

declare(strict_types=1);

namespace App\Domain\Calculation;

/** Résultat d'un calcul accepté : tout ce qu'il faut pour le montrer et pour le refaire. */
final readonly class CalculationResult
{
    /**
     * @param array<string, Quantity> $given entrées telles que fournies, par clé
     * @param list<CalculationStep>   $steps
     */
    public function __construct(
        public CalculatorDefinition $definition,
        public array $given,
        public array $steps,
        /** Valeur arrondie selon la règle du calculateur (CA6) */
        public Quantity $result,
        /** Valeur avant arrondi, dans l'unité du résultat */
        public float $exact,
    ) {
    }

    /**
     * Copie complète, enregistrée avec l'exécution : un calcul passé se relit tel qu'il a été
     * fait, même si le calculateur a changé de version depuis (CA5).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'definition' => $this->definition->toArray(),
            'given' => array_map(static fn (Quantity $quantity): array => ['value' => $quantity->value, 'unit' => $quantity->unit->value], $this->given),
            'steps' => array_map(static fn (CalculationStep $step): array => $step->toArray(), $this->steps),
            'result' => ['value' => $this->result->value, 'unit' => $this->result->unit->value],
            'exact' => $this->exact,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            CalculatorDefinition::fromArray($data['definition']),
            array_map(static fn (array $quantity): Quantity => new Quantity((float) $quantity['value'], Unit::from($quantity['unit'])), $data['given']),
            array_map(CalculationStep::fromArray(...), array_values($data['steps'])),
            new Quantity((float) $data['result']['value'], Unit::from($data['result']['unit'])),
            (float) $data['exact'],
        );
    }
}
