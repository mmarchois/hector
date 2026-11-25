<?php

declare(strict_types=1);

namespace App\Infrastructure\Evaluation;

/**
 * Ce qu'un cas attend d'un calcul (lot L14), vérifié sans modèle sur ce que le serveur a
 * montré : un résultat du bon calculateur, ou aucun résultat de calcul du tout.
 */
final readonly class ExpectedCalculation
{
    public function __construct(
        /** Calculateur dont un résultat doit être montré ; null : aucun résultat de calcul ne doit l'être */
        public ?string $calculator,
        /** Valeur attendue du résultat, telle que le calculateur l'arrondit ; null : non vérifiée */
        public ?float $value = null,
        public ?string $unit = null,
    ) {
    }
}
