<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Knowledge;

use App\Domain\Knowledge\Corpus;
use App\Domain\Knowledge\DocumentStatus;
use App\Domain\Knowledge\DocumentVersion;
use App\Domain\Knowledge\Exception\DocumentVersionImmutableException;
use App\Domain\Knowledge\Exception\DocumentVersionNotPublishableException;
use App\Domain\Knowledge\Exception\IndexingNotAllowedException;
use App\Domain\Knowledge\License;
use App\Domain\Knowledge\Passage;
use App\Domain\Knowledge\Source;
use App\Domain\Knowledge\SourceKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Lot L9 : cycle de vie d'une version de document et licences (KN1, KN2, KN4, KN5, KN6). */
final class DocumentVersionTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-25 10:00:00');
    }

    public function testACompleteAndReviewedVersionIsPublished(): void
    {
        $version = $this->version();

        $version->publish(' A. Expert ', $this->now);

        self::assertSame(DocumentStatus::Published, $version->getStatus());
        self::assertSame('A. Expert', $version->getReviewedBy());
        self::assertSame($this->now, $version->getReviewedAt());
    }

    /** @return iterable<string, array{array<string, mixed>, string, string}> */
    public static function incompleteVersions(): iterable
    {
        yield 'sans éditeur' => [['publisher' => ' '], 'A. Expert', 'éditeur'];
        yield 'sans libellé de version' => [['label' => ''], 'A. Expert', 'libellé de version'];
        yield 'sans date de publication' => [['publishedOn' => null], 'A. Expert', 'date de publication'];
        yield 'sans licence renseignée' => [['licenseOrigin' => ''], 'A. Expert', 'licence'];
        yield 'sans validation humaine' => [[], '  ', 'relecteur'];
    }

    /** @param array<string, mixed> $overrides */
    #[DataProvider('incompleteVersions')]
    public function testKn1AVersionMissingARequiredElementCannotBePublished(array $overrides, string $reviewer, string $missing): void
    {
        $version = $this->version(...$overrides);

        try {
            $version->publish($reviewer, $this->now);
            self::fail('Une DocumentVersionNotPublishableException était attendue.');
        } catch (DocumentVersionNotPublishableException $e) {
            self::assertSame([$missing], $e->missing);
            self::assertStringContainsString($missing, $e->getMessage());
        }
        self::assertSame(DocumentStatus::Draft, $version->getStatus());
    }

    public function testKn1EverythingMissingIsListedAtOnce(): void
    {
        try {
            $this->version(publisher: '', publishedOn: null)->publish('', $this->now);
            self::fail('Une DocumentVersionNotPublishableException était attendue.');
        } catch (DocumentVersionNotPublishableException $e) {
            self::assertSame(['éditeur', 'date de publication', 'relecteur'], $e->missing);
        }
    }

    public function testKn2APublishedVersionNoLongerChanges(): void
    {
        $version = $this->version();
        $version->publish('A. Expert', $this->now);

        foreach ([
            static fn () => $version->reingest('autre.md', str_repeat('b', 64), 'cle', null, null, null, null),
            fn () => $version->publish('B. Autre', $this->now),
        ] as $change) {
            try {
                $change();
                self::fail('Une DocumentVersionImmutableException était attendue.');
            } catch (DocumentVersionImmutableException) {
            }
        }
        self::assertSame('fiche.md', $version->getFileName());
        self::assertSame('A. Expert', $version->getReviewedBy());
    }

    public function testADraftCanBeIngestedAgain(): void
    {
        $version = $this->version(publishedOn: null);

        $version->reingest('fiche-v2.md', str_repeat('b', 64), 'cle-2', new \DateTimeImmutable('2026-11-01'), null, null, null);

        self::assertSame('fiche-v2.md', $version->getFileName());
        self::assertSame(str_repeat('b', 64), $version->getFileFingerprint());
        self::assertEquals(new \DateTimeImmutable('2026-11-01'), $version->getPublishedOn());
    }

    public function testKn4OnlyAPublishedVersionIsWithdrawnAndItStaysFrozen(): void
    {
        $draft = $this->version();
        try {
            $draft->withdraw($this->now);
            self::fail('Un brouillon ne se retire pas.');
        } catch (DocumentVersionImmutableException) {
        }

        $version = $this->version();
        $version->publish('A. Expert', $this->now);
        $version->withdraw($this->now->modify('+1 day'));

        self::assertSame(DocumentStatus::Withdrawn, $version->getStatus());
        self::assertEquals($this->now->modify('+1 day'), $version->getWithdrawnAt());
        $this->expectException(DocumentVersionImmutableException::class);
        $version->reingest('autre.md', str_repeat('b', 64), 'cle', null, null, null, null);
    }

    public function testKn5ASourceWhoseLicenseForbidsIndexingCannotBeIngested(): void
    {
        $this->source()->assertIndexable();

        $this->expectException(IndexingNotAllowedException::class);
        $this->expectExceptionMessage('Norme protégée');
        $this->source(title: 'Norme protégée', indexingAllowed: false)->assertIndexable();
    }

    public function testKn6TheFingerprintOfAPassageDependsOnlyOnItsLocationAndItsText(): void
    {
        $fingerprint = Passage::fingerprintOf(3, 'Rôle', '1', 'Texte du passage.');

        self::assertSame($fingerprint, Passage::fingerprintOf(3, 'Rôle', '1', 'Texte du passage.'));
        self::assertSame(64, \strlen($fingerprint));
        self::assertNotSame($fingerprint, Passage::fingerprintOf(4, 'Rôle', '1', 'Texte du passage.'));
        self::assertNotSame($fingerprint, Passage::fingerprintOf(3, 'Limites', '1', 'Texte du passage.'));
        self::assertNotSame($fingerprint, Passage::fingerprintOf(3, 'Rôle', '1', 'Texte du passage modifié.'));
    }

    private function source(string $title = 'Fiche d\'exemple', string $publisher = 'Hector', string $licenseOrigin = 'Contenu rédigé pour Hector', bool $indexingAllowed = true): Source
    {
        return new Source('s1', Corpus::Regulatory, $title, $publisher, SourceKind::HectorSheet, new License($indexingAllowed, true, null, true, $licenseOrigin), [], $this->now);
    }

    private function version(
        string $publisher = 'Hector',
        string $label = 'v1',
        ?\DateTimeImmutable $publishedOn = new \DateTimeImmutable('2026-11-01'),
        string $licenseOrigin = 'Contenu rédigé pour Hector',
    ): DocumentVersion {
        return new DocumentVersion('v1', $this->source(publisher: $publisher, licenseOrigin: $licenseOrigin), $label, $publishedOn, null, null, null, 'fiche.md', str_repeat('a', 64), 'cle', $this->now);
    }
}
