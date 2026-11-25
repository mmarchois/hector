<?php

declare(strict_types=1);

namespace App\Domain\Calculation;

/**
 * Un calcul défini et versionné. Fonction pure (CA1) : mêmes entrées, même résultat ; ni
 * horloge, ni base de données, ni modèle de langage.
 */
interface CalculatorInterface
{
    public function definition(): CalculatorDefinition;

    /**
     * @param array<string, float> $inputs valeurs déjà validées, dans l'unité de chaque entrée
     */
    public function compute(array $inputs): Computation;
}
