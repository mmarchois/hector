<?php

declare(strict_types=1);

namespace App\Domain\Calculation;

/** Nature physique d'une grandeur : deux grandeurs ne se convertissent qu'au sein d'une même dimension (CA2). */
enum Dimension: string
{
    case Voltage = 'voltage';
    case Current = 'current';
    case Resistance = 'resistance';
    case Power = 'power';
    case Ratio = 'ratio';

    /** Nom de la grandeur, tel qu'il apparaît dans un refus. */
    public function label(): string
    {
        return match ($this) {
            self::Voltage => 'tension',
            self::Current => 'courant',
            self::Resistance => 'résistance',
            self::Power => 'puissance',
            self::Ratio => 'rapport sans unité',
        };
    }
}
