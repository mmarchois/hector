<?php

declare(strict_types=1);

namespace App\Tests\Critical\Calculation;

use App\Domain\Calculation\CalculationEngine;
use App\Domain\Calculation\CalculatorCatalog;
use App\Domain\Calculation\CalculatorInterface;
use App\Domain\Calculation\Exception\CalculationRefusedException;
use App\Domain\Calculation\Refusal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cas de référence du calcul (lot L13), tenus dans un fichier de données indépendant du code :
 * reference-cases.json. Chaque valeur attendue y a été posée à la main puis recoupée par un
 * calcul décimal fait hors de PHP. Ils attendent la relecture d'un expert (champ review).
 */
final class ReferenceCasesTest extends TestCase
{
    private const string FILE = __DIR__ . '/reference-cases.json';

    /**
     * @param array<string, mixed> $case
     */
    #[DataProvider('acceptedCases')]
    public function testAnAcceptedCaseGivesTheExpectedResult(array $case): void
    {
        $result = CalculationEngine::run((new CalculatorCatalog())->get($case['calculator']), $case['inputs']);

        self::assertSame((float) $case['expected']['value'], $result->result->value, $case['derivation']);
        self::assertSame($case['expected']['unit'], $result->result->unit->value);
    }

    /**
     * @param array<string, mixed> $case
     */
    #[DataProvider('refusedCases')]
    public function testARefusedCaseIsRefusedOnTheExpectedInputs(array $case): void
    {
        try {
            CalculationEngine::run((new CalculatorCatalog())->get($case['calculator']), $case['inputs']);
        } catch (CalculationRefusedException $e) {
            self::assertEqualsCanonicalizing(
                $case['refusedInputs'],
                array_map(static fn (Refusal $refusal): string => $refusal->input, $e->refusals),
                $case['why'],
            );
            foreach ($e->refusals as $refusal) {
                self::assertNotSame('', $refusal->message());
            }

            return;
        }

        self::fail(\sprintf('Ce calcul aurait dû être refusé. %s', $case['why']));
    }

    public function testEveryCalculatorHasReferenceCases(): void
    {
        $covered = array_count_values(array_column(self::load()['accepted'], 'calculator'));

        foreach ((new CalculatorCatalog())->all() as $calculator) {
            $key = $calculator->definition()->key;
            self::assertGreaterThanOrEqual(2, $covered[$key] ?? 0, \sprintf('Le calculateur %s n\'a pas assez de cas de référence.', $key));
        }
        // Et aucun cas ne vise un calculateur qui n'existe plus
        self::assertSame(
            [],
            array_diff(array_keys($covered), array_map(static fn (CalculatorInterface $calculator): string => $calculator->definition()->key, (new CalculatorCatalog())->all())),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function acceptedCases(): iterable
    {
        foreach (self::load()['accepted'] as $case) {
            yield $case['id'] => [$case];
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function refusedCases(): iterable
    {
        foreach (self::load()['refused'] as $case) {
            yield $case['id'] => [$case];
        }
    }

    /**
     * @return array{accepted: list<array<string, mixed>>, refused: list<array<string, mixed>>}
     */
    private static function load(): array
    {
        return json_decode((string) file_get_contents(self::FILE), true, flags: \JSON_THROW_ON_ERROR);
    }
}
