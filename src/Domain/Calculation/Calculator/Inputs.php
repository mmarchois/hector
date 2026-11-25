<?php

declare(strict_types=1);

namespace App\Domain\Calculation\Calculator;

use App\Domain\Calculation\InputDefinition;
use App\Domain\Calculation\Unit;

/**
 * Entrées communes aux calculateurs, avec leurs bornes. Les bornes hautes délimitent ce
 * qu'Hector traite, la basse tension : elles arrêtent une faute de frappe, elles ne disent
 * pas qu'une valeur en dessous convient à une installation donnée.
 */
final class Inputs
{
    public const float MAX_VOLTAGE = 1000.0;
    public const float MAX_CURRENT = 10_000.0;
    public const float MAX_RESISTANCE = 1_000_000_000.0;
    public const float MAX_POWER = 10_000_000.0;

    public static function voltage(string $label = 'Tension'): InputDefinition
    {
        return new InputDefinition('voltage', $label, 'U', Unit::Volt, 0.0, self::MAX_VOLTAGE);
    }

    public static function current(string $label = 'Courant'): InputDefinition
    {
        return new InputDefinition('current', $label, 'I', Unit::Ampere, 0.0, self::MAX_CURRENT);
    }

    public static function resistance(): InputDefinition
    {
        return new InputDefinition('resistance', 'Résistance', 'R', Unit::Ohm, 0.0, self::MAX_RESISTANCE);
    }

    public static function power(): InputDefinition
    {
        return new InputDefinition('power', 'Puissance active', 'P', Unit::Watt, 0.0, self::MAX_POWER);
    }

    public static function powerFactor(): InputDefinition
    {
        return new InputDefinition('powerFactor', 'Facteur de puissance', 'cos φ', Unit::None, 0.0, 1.0);
    }
}
