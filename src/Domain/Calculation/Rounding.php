<?php

declare(strict_types=1);

namespace App\Domain\Calculation;

/**
 * Règle d'arrondi d'un résultat, explicite (CA6). La précision est la même pour tous les
 * calculs : quatre chiffres significatifs, sans jamais arrondir la partie entière
 * (18 844,7 donne 18 845 ; 14,4928 donne 14,49 ; 0,00051234 donne 0,0005123). Seul le sens
 * change : au plus proche, ou par excès quand sous-estimer serait le côté dangereux.
 */
enum Rounding: string
{
    case Nearest = 'nearest';
    case Up = 'up';

    public const int SIGNIFICANT_DIGITS = 4;

    /**
     * Écart relatif en dessous duquel une valeur est tenue pour déjà ronde : 16,000000000000004,
     * né du calcul en virgule flottante, ne doit pas devenir 16,01 par excès.
     */
    private const float TOLERANCE = 1.0e-9;

    public function apply(float $value): float
    {
        if ($value === 0.0) {
            return 0.0;
        }

        $factor = 10.0 ** self::decimalsFor($value);
        $scaled = $value * $factor;
        // Valeur si petite que la décaler dépasse ce qu'un nombre flottant sait porter : laissée telle quelle
        if (!is_finite($factor) || !is_finite($scaled)) {
            return $value;
        }

        $nearest = round($scaled);
        if ($this === self::Nearest || abs($scaled - $nearest) <= abs($scaled) * self::TOLERANCE) {
            return $nearest / $factor;
        }

        return ceil($scaled) / $factor;
    }

    /** Nombre de décimales gardées pour cette valeur. */
    public static function decimalsFor(float $value): int
    {
        if ($value === 0.0) {
            return 0;
        }

        return max(0, self::SIGNIFICANT_DIGITS - 1 - (int) floor(log10(abs($value))));
    }

    /** Règle en clair, montrée avec la définition du calcul. */
    public function rule(): string
    {
        return match ($this) {
            self::Nearest => 'Arrondi au plus proche, à quatre chiffres significatifs ; la partie entière n\'est jamais arrondie.',
            self::Up => 'Arrondi par excès, à quatre chiffres significatifs ; la partie entière n\'est jamais arrondie. Un courant à prévoir ne se sous-estime pas.',
        };
    }
}
