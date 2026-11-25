<?php

declare(strict_types=1);

namespace App\Domain\Calculation;

/** Ce qu'un calculateur rend : ses étapes et sa valeur, dans l'unité de sa sortie, avant arrondi. */
final readonly class Computation
{
    /**
     * @param list<CalculationStep> $steps
     */
    public function __construct(
        public array $steps,
        public float $value,
    ) {
    }
}
