<?php

declare(strict_types=1);

namespace App\Tests\Critical\Calculation;

use App\Domain\Calculation\Rounding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * CA6 : la règle d'arrondi est explicite. Quatre chiffres significatifs, partie entière
 * jamais arrondie ; au plus proche, ou par excès quand sous-estimer serait dangereux.
 */
final class RoundingTest extends TestCase
{
    #[DataProvider('nearest')]
    public function testToTheNearest(float $value, float $expected): void
    {
        self::assertSame($expected, Rounding::Nearest->apply($value));
    }

    /**
     * @return iterable<string, array{float, float}>
     */
    public static function nearest(): iterable
    {
        yield 'partie entière gardée' => [18844.7, 18845.0];
        yield 'grand nombre' => [123456.7, 123457.0];
        yield 'déjà entier' => [3312.0, 3312.0];
        yield 'deux décimales' => [14.4928, 14.49];
        yield 'le demi monte' => [14.375, 14.38];
        yield 'une décimale' => [230.04, 230.0];
        yield 'tiers' => [1666.6666666666667, 1667.0];
        yield 'passage à la dizaine' => [9.99996, 10.0];
        yield 'petite valeur' => [0.00051234, 0.0005123];
        yield 'zéro' => [0.0, 0.0];
    }

    #[DataProvider('up')]
    public function testUpwards(float $value, float $expected): void
    {
        self::assertSame($expected, Rounding::Up->apply($value));
    }

    /**
     * @return iterable<string, array{float, float}>
     */
    public static function up(): iterable
    {
        yield 'par excès' => [16.304347826086957, 16.31];
        yield 'déjà rond' => [16.0, 16.0];
        yield 'rond au bruit du calcul flottant près' => [16.000000000000004, 16.0];
        yield 'vraiment au-dessus' => [16.0001, 16.01];
        yield 'trois décimales' => [5.773502691896258, 5.774];
        yield 'huit virgule sept' => [8.695652173913043, 8.696];
        yield 'partie entière' => [18844.2, 18845.0];
        yield 'petite valeur' => [0.00051231, 0.0005124];
        yield 'déjà à la précision' => [14.49, 14.49];
        yield 'zéro' => [0.0, 0.0];
    }

    #[DataProvider('decimals')]
    public function testTheNumberOfDecimalsFollowsTheSizeOfTheValue(float $value, int $expected): void
    {
        self::assertSame($expected, Rounding::decimalsFor($value));
    }

    /**
     * @return iterable<string, array{float, int}>
     */
    public static function decimals(): iterable
    {
        yield 'dizaines de milliers' => [18844.7, 0];
        yield 'mille' => [1000.0, 0];
        yield 'centaines' => [999.9, 1];
        yield 'dizaines' => [14.49, 2];
        yield 'unités' => [1.0, 3];
        yield 'dixièmes' => [0.5, 4];
        yield 'dix-millièmes' => [0.0005, 7];
        yield 'zéro' => [0.0, 0];
    }

    public function testRoundingStaysWithinOneStepAndUpNeverUnderestimates(): void
    {
        $random = new Randomizer(new Mt19937(20261125));

        for ($i = 0; $i < 2000; ++$i) {
            // De 0,000 001 à 10 000 000, toutes tailles confondues
            $value = 10 ** $random->getFloat(-6.0, 7.0);
            $step = 10.0 ** -Rounding::decimalsFor($value);
            $nearest = Rounding::Nearest->apply($value);
            $up = Rounding::Up->apply($value);

            self::assertEqualsWithDelta($value, $nearest, $step / 2 * (1 + 1e-9), (string) $value);
            self::assertGreaterThanOrEqual($value * (1 - 1e-9), $up, (string) $value);
            self::assertLessThan($value + $step * (1 + 1e-9), $up, (string) $value);
            self::assertGreaterThanOrEqual($nearest, $up, (string) $value);
            // Arrondir une valeur déjà arrondie ne la change plus
            self::assertSame($nearest, Rounding::Nearest->apply($nearest), (string) $value);
            self::assertSame($up, Rounding::Up->apply($up), (string) $value);
        }
    }

    public function testAValueTooSmallToBeShiftedIsLeftAsItIs(): void
    {
        self::assertSame(1.0e-320, Rounding::Nearest->apply(1.0e-320));
        self::assertSame(1.0e-320, Rounding::Up->apply(1.0e-320));
    }

    public function testEachDirectionSaysItsRule(): void
    {
        self::assertStringContainsString('au plus proche', Rounding::Nearest->rule());
        self::assertStringContainsString('par excès', Rounding::Up->rule());
        foreach (Rounding::cases() as $rounding) {
            self::assertStringContainsString('quatre chiffres significatifs', $rounding->rule());
        }
        self::assertSame(4, Rounding::SIGNIFICANT_DIGITS);
    }
}
