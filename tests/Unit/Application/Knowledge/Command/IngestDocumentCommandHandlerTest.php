<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Knowledge\Command;

use App\Application\Knowledge\Command\IngestDocumentCommand;
use App\Application\Knowledge\Command\IngestDocumentCommandHandler;
use App\Application\Knowledge\Port\ChunkerInterface;
use App\Application\Knowledge\Port\ExtractedPage;
use App\Application\Knowledge\Port\FileStorageInterface;
use App\Application\Knowledge\Port\PassageDraft;
use App\Application\Knowledge\Port\StoredFile;
use App\Application\Knowledge\Port\TextExtractorInterface;
use App\Domain\Knowledge\Corpus;
use App\Domain\Knowledge\DocumentVersion;
use App\Domain\Knowledge\Exception\DocumentVersionImmutableException;
use App\Domain\Knowledge\Exception\IndexingNotAllowedException;
use App\Domain\Knowledge\Exception\SourceNotFoundException;
use App\Domain\Knowledge\Exception\UnsupportedDocumentFormatException;
use App\Domain\Knowledge\IngestionRun;
use App\Domain\Knowledge\License;
use App\Domain\Knowledge\Passage;
use App\Domain\Knowledge\Repository\DocumentVersionRepositoryInterface;
use App\Domain\Knowledge\Repository\IngestionRunRepositoryInterface;
use App\Domain\Knowledge\Repository\PassageRepositoryInterface;
use App\Domain\Knowledge\Repository\SourceRepositoryInterface;
use App\Domain\Knowledge\Source;
use App\Domain\Knowledge\SourceKind;
use App\Domain\Port\DateUtilsInterface;
use App\Domain\Port\IdFactoryInterface;
use PHPUnit\Framework\TestCase;

/** Lot L9 : ingestion d'un document. */
final class IngestDocumentCommandHandlerTest extends TestCase
{
    private const string SOURCE = '0b507871-8b5e-4575-b297-a630310fc06e';

    private \DateTimeImmutable $now;
    /** @var list<Passage> */
    private array $passages = [];
    /** @var list<IngestionRun> */
    private array $runs = [];
    private int $removals = 0;
    private int $stored = 0;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-25 10:00:00');
        $this->passages = [];
        $this->runs = [];
        $this->removals = 0;
        $this->stored = 0;
    }

    public function testADocumentBecomesADraftVersionWithItsLocatedPassages(): void
    {
        $version = $this->handler($this->source())(new IngestDocumentCommand(self::SOURCE, '/tmp/fiche.md', ' v1 ', '2026-11-01'));

        self::assertTrue($version->isDraft());
        self::assertSame('v1', $version->getLabel());
        self::assertSame('fiche.md', $version->getFileName());
        self::assertSame(str_repeat('a', 64), $version->getFileFingerprint());
        self::assertEquals(new \DateTimeImmutable('2026-11-01 00:00:00'), $version->getPublishedOn());

        self::assertSame([1, 2], array_map(static fn (Passage $passage): int => $passage->getPosition(), $this->passages));
        self::assertSame([3, 4], array_map(static fn (Passage $passage): ?int => $passage->getPage(), $this->passages));
        self::assertSame('Rôle', $this->passages[0]->getSection());
        self::assertSame(Passage::fingerprintOf(3, 'Rôle', null, 'Premier passage.'), $this->passages[0]->getFingerprint());

        // La trace d'ingestion dit avec quelle chaîne les passages ont été produits (KN6)
        self::assertCount(1, $this->runs);
        self::assertSame(['1', 'faux-extracteur@1', 'faux-decoupeur@1', ['taille' => 10], 2], [
            $this->runs[0]->getPipelineVersion(),
            $this->runs[0]->getExtractor(),
            $this->runs[0]->getChunker(),
            $this->runs[0]->getChunkerParameters(),
            $this->runs[0]->getPassageCount(),
        ]);
    }

    public function testIngestingADraftAgainReplacesItsPassages(): void
    {
        $draft = new DocumentVersion('version-1', $this->source(), 'v1', null, null, null, null, 'ancien.md', str_repeat('f', 64), 'ancienne-cle', $this->now);

        $version = $this->handler($this->source(), $draft)(new IngestDocumentCommand(self::SOURCE, '/tmp/fiche.md', 'v1'));

        self::assertSame($draft, $version);
        self::assertSame(1, $this->removals);
        self::assertSame('fiche.md', $version->getFileName());
        self::assertCount(2, $this->passages);
    }

    public function testKn2APublishedVersionIsNotIngestedAgain(): void
    {
        $published = new DocumentVersion('version-1', $this->source(), 'v1', $this->now, null, null, null, 'fiche.md', str_repeat('f', 64), 'cle', $this->now);
        $published->publish('A. Expert', $this->now);

        try {
            $this->handler($this->source(), $published)(new IngestDocumentCommand(self::SOURCE, '/tmp/fiche.md', 'v1'));
            self::fail('Une DocumentVersionImmutableException était attendue.');
        } catch (DocumentVersionImmutableException) {
        }
        // Rien n'est conservé ni remplacé
        self::assertSame([0, 0, []], [$this->stored, $this->removals, $this->passages]);
    }

    public function testKn5ASourceThatCannotBeIndexedIsRefusedBeforeAnythingIsStored(): void
    {
        try {
            $this->handler($this->source(indexingAllowed: false))(new IngestDocumentCommand(self::SOURCE, '/tmp/norme.md', 'édition 2024'));
            self::fail('Une IndexingNotAllowedException était attendue.');
        } catch (IndexingNotAllowedException) {
        }
        self::assertSame(0, $this->stored);
    }

    public function testAnUnknownSourceOrAnUnreadableFormatIsRefused(): void
    {
        try {
            $this->handler(null)(new IngestDocumentCommand(self::SOURCE, '/tmp/fiche.md', 'v1'));
            self::fail('Une SourceNotFoundException était attendue.');
        } catch (SourceNotFoundException) {
        }

        $this->expectException(UnsupportedDocumentFormatException::class);
        $this->expectExceptionMessage('norme.pdf');
        $this->handler($this->source())(new IngestDocumentCommand(self::SOURCE, '/tmp/norme.pdf', 'v1'));
    }

    private function source(bool $indexingAllowed = true): Source
    {
        return new Source(self::SOURCE, Corpus::Regulatory, 'Fiche', 'Hector', SourceKind::HectorSheet, new License($indexingAllowed, true, null, true, 'Contenu rédigé pour Hector'), [], $this->now);
    }

    private function handler(?Source $source, ?DocumentVersion $existing = null): IngestDocumentCommandHandler
    {
        $sources = $this->createStub(SourceRepositoryInterface::class);
        $sources->method('findOneByUuid')->willReturn($source);

        $versions = $this->createStub(DocumentVersionRepositoryInterface::class);
        $versions->method('findOneBySourceAndLabel')->willReturn($existing);
        $versions->method('add')->willReturnArgument(0);

        $passages = $this->createStub(PassageRepositoryInterface::class);
        $passages->method('add')->willReturnCallback(function (Passage $passage): Passage {
            $this->passages[] = $passage;

            return $passage;
        });
        $passages->method('removeByVersion')->willReturnCallback(function (): void {
            ++$this->removals;
        });

        $runs = $this->createStub(IngestionRunRepositoryInterface::class);
        $runs->method('add')->willReturnCallback(function (IngestionRun $run): IngestionRun {
            $this->runs[] = $run;

            return $run;
        });

        $storage = $this->createStub(FileStorageInterface::class);
        $storage->method('store')->willReturnCallback(function (): StoredFile {
            ++$this->stored;

            return new StoredFile(str_repeat('a', 64) . '.md', str_repeat('a', 64));
        });
        $storage->method('path')->willReturn('/stockage/fiche.md');

        $extractor = $this->createStub(TextExtractorInterface::class);
        $extractor->method('supports')->willReturnCallback(static fn (string $name): bool => str_ends_with($name, '.md'));
        $extractor->method('name')->willReturn('faux-extracteur@1');
        $extractor->method('extract')->willReturn([new ExtractedPage(3, 'Texte')]);

        $chunker = $this->createStub(ChunkerInterface::class);
        $chunker->method('name')->willReturn('faux-decoupeur@1');
        $chunker->method('parameters')->willReturn(['taille' => 10]);
        $chunker->method('chunk')->willReturn([
            new PassageDraft(3, 'Rôle', null, 'Premier passage.'),
            new PassageDraft(4, 'Rôle', '2', 'Second passage.'),
        ]);

        $ids = $this->createStub(IdFactoryInterface::class);
        $ids->method('make')->willReturnCallback(static fn (): string => bin2hex(random_bytes(8)));
        $dates = $this->createStub(DateUtilsInterface::class);
        $dates->method('getNow')->willReturn($this->now);

        return new IngestDocumentCommandHandler($sources, $versions, $passages, $runs, $storage, [$extractor], $chunker, $ids, $dates);
    }
}
