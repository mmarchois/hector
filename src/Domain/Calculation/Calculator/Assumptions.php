<?php

declare(strict_types=1);

namespace App\Domain\Calculation\Calculator;

/** Hypothèses des calculateurs, dites avec chaque résultat (CA4). */
final class Assumptions
{
    public const string RESISTIVE = 'Courant continu, ou courant alternatif sur un récepteur purement résistif.';
    public const string CONSTANT_RESISTANCE = 'La résistance est supposée constante : son échauffement n\'est pas pris en compte.';
    public const string SINUSOIDAL = 'Régime sinusoïdal établi ; tension et courant en valeurs efficaces.';
    public const string POWER_FACTOR_GIVEN = 'Le facteur de puissance est celui que vous indiquez : aucune valeur n\'est supposée.';
    public const string BALANCED = 'Réseau triphasé équilibré : même courant dans les trois phases.';
    public const string LINE_VOLTAGE = 'La tension est celle mesurée entre deux phases (400 V sur un réseau 230/400 V).';
    public const string NO_COEFFICIENT = 'La puissance est la puissance active absorbée : ni rendement, ni coefficient d\'utilisation ou de simultanéité ne sont appliqués.';
}
