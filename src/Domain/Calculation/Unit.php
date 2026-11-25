<?php

declare(strict_types=1);

namespace App\Domain\Calculation;

/** Unités acceptées, chacune avec sa dimension et son rapport à l'unité de base (CA2). */
enum Unit: string
{
    case Volt = 'V';
    case Millivolt = 'mV';
    case Kilovolt = 'kV';
    case Ampere = 'A';
    case Milliampere = 'mA';
    case Kiloampere = 'kA';
    case Ohm = "\u{03A9}";
    case Milliohm = "m\u{03A9}";
    case Kiloohm = "k\u{03A9}";
    case Megaohm = "M\u{03A9}";
    case Watt = 'W';
    case Kilowatt = 'kW';
    /** Grandeur sans unité : facteur de puissance */
    case None = '';

    /** Écritures tolérées en entrée : tout le monde n'a pas « Ω » sous le doigt. */
    private const array ALIASES = ['ohm' => "\u{03A9}", 'ohms' => "\u{03A9}", 'mohm' => "m\u{03A9}", 'kohm' => "k\u{03A9}", 'Mohm' => "M\u{03A9}"];

    /** Unité écrite par ce symbole ; null si elle n'est pas connue. La casse compte : mV n'est pas MV. */
    public static function fromSymbol(string $symbol): ?self
    {
        // Le signe « ohm » (U+2126) et la lettre oméga (U+03A9) s'affichent pareil
        $symbol = str_replace("\u{2126}", "\u{03A9}", trim($symbol));

        return self::tryFrom(self::ALIASES[$symbol] ?? $symbol);
    }

    public function dimension(): Dimension
    {
        return match ($this) {
            self::Volt, self::Millivolt, self::Kilovolt => Dimension::Voltage,
            self::Ampere, self::Milliampere, self::Kiloampere => Dimension::Current,
            self::Ohm, self::Milliohm, self::Kiloohm, self::Megaohm => Dimension::Resistance,
            self::Watt, self::Kilowatt => Dimension::Power,
            self::None => Dimension::Ratio,
        };
    }

    /** Combien d'unités de base vaut une unité : 1 kV = 1 000 V. */
    public function factor(): float
    {
        return match ($this) {
            self::Millivolt, self::Milliampere, self::Milliohm => 0.001,
            self::Kilovolt, self::Kiloampere, self::Kiloohm, self::Kilowatt => 1000.0,
            self::Megaohm => 1_000_000.0,
            self::Volt, self::Ampere, self::Ohm, self::Watt, self::None => 1.0,
        };
    }

    /** @return list<self> unités d'une dimension, l'unité de base en premier */
    public static function of(Dimension $dimension): array
    {
        return array_values(array_filter(self::cases(), static fn (self $unit): bool => $unit->dimension() === $dimension));
    }
}
