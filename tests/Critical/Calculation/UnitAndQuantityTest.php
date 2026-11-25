<?php

declare(strict_types=1);

namespace App\Tests\Critical\Calculation;

use App\Domain\Calculation\Dimension;
use App\Domain\Calculation\Quantity;
use App\Domain\Calculation\Unit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** CA2 : aucun nombre sans unité, et les conversions passent par les grandeurs. */
final class UnitAndQuantityTest extends TestCase
{
    #[DataProvider('conversions')]
    public function testAKnownConversion(float $value, Unit $from, Unit $to, float $expected): void
    {
        self::assertEqualsWithDelta($expected, (new Quantity($value, $from))->to($to)->value, abs($expected) * 1e-12);
    }

    /**
     * @return iterable<string, array{float, Unit, Unit, float}>
     */
    public static function conversions(): iterable
    {
        yield '1 kV = 1 000 V' => [1.0, Unit::Kilovolt, Unit::Volt, 1000.0];
        yield '230 V = 0,23 kV' => [230.0, Unit::Volt, Unit::Kilovolt, 0.23];
        yield '500 mV = 0,5 V' => [500.0, Unit::Millivolt, Unit::Volt, 0.5];
        yield '30 mA = 0,03 A' => [30.0, Unit::Milliampere, Unit::Ampere, 0.03];
        yield '6 kA = 6 000 A' => [6.0, Unit::Kiloampere, Unit::Ampere, 6000.0];
        yield '2,5 MΩ = 2 500 000 Ω' => [2.5, Unit::Megaohm, Unit::Ohm, 2_500_000.0];
        yield '2,5 MΩ = 2 500 kΩ' => [2.5, Unit::Megaohm, Unit::Kiloohm, 2500.0];
        yield '500 mΩ = 0,5 Ω' => [500.0, Unit::Milliohm, Unit::Ohm, 0.5];
        yield '3 kW = 3 000 W' => [3.0, Unit::Kilowatt, Unit::Watt, 3000.0];
        yield '0,9 reste 0,9' => [0.9, Unit::None, Unit::None, 0.9];
    }

    public function testAConversionAndItsReverseGiveBackTheValue(): void
    {
        foreach (Dimension::cases() as $dimension) {
            foreach (Unit::of($dimension) as $from) {
                foreach (Unit::of($dimension) as $to) {
                    foreach ([0.001, 1.0, 230.0, 12345.678] as $value) {
                        $back = (new Quantity($value, $from))->to($to)->to($from);

                        self::assertSame($from, $back->unit);
                        self::assertEqualsWithDelta($value, $back->value, $value * 1e-12, \sprintf('%s %s → %s', $value, $from->value, $to->value));
                    }
                }
            }
        }
    }

    public function testAQuantityIsNotConvertedIntoAnotherKindOfQuantity(): void
    {
        foreach (Unit::cases() as $from) {
            foreach (Unit::cases() as $to) {
                if ($from->dimension() === $to->dimension()) {
                    continue;
                }
                try {
                    (new Quantity(1.0, $from))->to($to);
                    self::fail(\sprintf('%s ne se convertit pas en %s.', $from->name, $to->name));
                } catch (\InvalidArgumentException $e) {
                    self::assertStringContainsString($to->dimension()->label(), $e->getMessage());
                }
            }
        }
    }

    public function testAQuantityHasAFiniteValue(): void
    {
        foreach ([\INF, -\INF, \NAN] as $value) {
            try {
                new Quantity($value, Unit::Volt);
                self::fail('Une valeur non finie aurait dû être refusée.');
            } catch (\InvalidArgumentException $e) {
                self::assertSame('Une grandeur a une valeur finie.', $e->getMessage());
            }
        }
    }

    public function testEveryDimensionHasItsBaseUnitFirstAndEveryUnitBelongsToOne(): void
    {
        $listed = [];
        foreach (Dimension::cases() as $dimension) {
            $units = Unit::of($dimension);

            self::assertNotSame([], $units, $dimension->value);
            self::assertSame(1.0, $units[0]->factor(), $dimension->value);
            self::assertNotSame('', $dimension->label());
            foreach ($units as $unit) {
                self::assertSame($dimension, $unit->dimension());
                self::assertGreaterThan(0.0, $unit->factor());
            }
            $listed = [...$listed, ...$units];
        }

        self::assertEqualsCanonicalizing(Unit::cases(), $listed);
        self::assertSame(['Ω', 'mΩ', 'kΩ', 'MΩ'], array_map(static fn (Unit $unit): string => $unit->value, Unit::of(Dimension::Resistance)));
    }

    #[DataProvider('symbols')]
    public function testAUnitIsReadFromItsSymbol(string $symbol, ?Unit $expected): void
    {
        self::assertSame($expected, Unit::fromSymbol($symbol));
    }

    /**
     * @return iterable<string, array{string, Unit|null}>
     */
    public static function symbols(): iterable
    {
        yield 'volt' => ['V', Unit::Volt];
        yield 'espaces autour' => [' kW ', Unit::Kilowatt];
        yield 'oméga' => ["\u{03A9}", Unit::Ohm];
        yield 'signe ohm' => ["\u{2126}", Unit::Ohm];
        yield 'kilo et signe ohm' => ["k\u{2126}", Unit::Kiloohm];
        yield 'ohm en lettres' => ['ohm', Unit::Ohm];
        yield 'ohms en lettres' => ['ohms', Unit::Ohm];
        yield 'milliohm en lettres' => ['mohm', Unit::Milliohm];
        yield 'kilohm en lettres' => ['kohm', Unit::Kiloohm];
        yield 'mégohm en lettres' => ['Mohm', Unit::Megaohm];
        yield 'sans unité' => ['', Unit::None];
        yield 'minuscule' => ['v', null];
        yield 'mégavolt, pas millivolt' => ['MV', null];
        yield 'hertz' => ['Hz', null];
        yield 'pourcentage' => ['%', null];
    }
}
