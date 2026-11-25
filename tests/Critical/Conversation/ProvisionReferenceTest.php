<?php

declare(strict_types=1);

namespace App\Tests\Critical\Conversation;

use App\Domain\Conversation\ProvisionReference;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Détection des renvois à une disposition précise, sur les cas de référence de
 * provision-references.json (fichier indépendant du code, relu par l'expert).
 */
final class ProvisionReferenceTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function references(): iterable
    {
        foreach (self::cases()['references'] as $case) {
            yield $case['text'] => [$case['text'], $case['found']];
        }
    }

    /** @return iterable<string, array{string}> */
    public static function textsWithoutReference(): iterable
    {
        foreach (self::cases()['no_reference'] as $text) {
            yield $text => [$text];
        }
    }

    #[DataProvider('references')]
    public function testAPreciseReferenceIsFound(string $text, string $expected): void
    {
        self::assertSame($expected, ProvisionReference::find($text));
    }

    #[DataProvider('textsWithoutReference')]
    public function testAnOrdinarySentenceIsLeftAlone(string $text): void
    {
        self::assertNull(ProvisionReference::find($text));
    }

    /** @return array{references: list<array{text: string, found: string}>, no_reference: list<string>} */
    private static function cases(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/provision-references.json'), true, flags: \JSON_THROW_ON_ERROR);
    }
}
