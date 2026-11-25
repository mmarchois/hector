<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Command;

use App\Application\Knowledge\Port\ChunkerInterface;
use App\Application\Knowledge\Port\FileStorageInterface;
use App\Application\Knowledge\Port\TextExtractorInterface;
use App\Domain\Knowledge\DocumentVersion;
use App\Domain\Knowledge\Exception\DocumentVersionImmutableException;
use App\Domain\Knowledge\Exception\IndexingNotAllowedException;
use App\Domain\Knowledge\Exception\SourceNotFoundException;
use App\Domain\Knowledge\Exception\UnsupportedDocumentFormatException;
use App\Domain\Knowledge\IngestionRun;
use App\Domain\Knowledge\Passage;
use App\Domain\Knowledge\Repository\DocumentVersionRepositoryInterface;
use App\Domain\Knowledge\Repository\IngestionRunRepositoryInterface;
use App\Domain\Knowledge\Repository\PassageRepositoryInterface;
use App\Domain\Knowledge\Repository\SourceRepositoryInterface;
use App\Domain\Knowledge\Source;
use App\Domain\Port\DateUtilsInterface;
use App\Domain\Port\IdFactoryInterface;

final readonly class IngestDocumentCommandHandler
{
    /**
     * Version de la chaîne de traitement. À changer avec tout ce qui modifie les passages
     * produits pour un même fichier, hors extracteur et découpeur, qui portent la leur (KN6).
     */
    public const string PIPELINE_VERSION = '1';

    /**
     * @param iterable<TextExtractorInterface> $textExtractors
     */
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
        private DocumentVersionRepositoryInterface $versionRepository,
        private PassageRepositoryInterface $passageRepository,
        private IngestionRunRepositoryInterface $runRepository,
        private FileStorageInterface $storage,
        private iterable $textExtractors,
        private ChunkerInterface $chunker,
        private IdFactoryInterface $idFactory,
        private DateUtilsInterface $dateUtils,
    ) {
    }

    /**
     * @throws SourceNotFoundException
     * @throws IndexingNotAllowedException        KN5
     * @throws UnsupportedDocumentFormatException
     * @throws DocumentVersionImmutableException  KN2 : la version de ce libellé est déjà publiée
     */
    public function __invoke(IngestDocumentCommand $command): DocumentVersion
    {
        $source = $this->sourceRepository->findOneByUuid($command->sourceUuid);
        if (!$source instanceof Source) {
            throw new SourceNotFoundException();
        }
        // KN5 : refusé avant de lire ou de conserver quoi que ce soit
        $source->assertIndexable();

        $fileName = basename($command->filePath);
        $extractor = $this->extractorFor($fileName);

        $label = trim($command->label);
        $publishedOn = $this->date($command->publishedOn);
        $effectiveFrom = $this->date($command->effectiveFrom);
        $effectiveUntil = $this->date($command->effectiveUntil);
        $now = $this->dateUtils->getNow();

        $existing = $this->versionRepository->findOneBySourceAndLabel($source, $label);
        // KN2 : une version publiée ne se réingère pas
        if ($existing !== null && !$existing->isDraft()) {
            throw new DocumentVersionImmutableException('Une version publiée ou retirée ne change plus : publiez une nouvelle version.');
        }

        $stored = $this->storage->store($command->filePath);
        if ($existing !== null) {
            $version = $existing;
            $version->reingest($fileName, $stored->fingerprint, $stored->key, $publishedOn, $effectiveFrom, $effectiveUntil, $command->replacesUuid);
            $this->passageRepository->removeByVersion($version);
        } else {
            $version = $this->versionRepository->add(new DocumentVersion(
                $this->idFactory->make(),
                $source,
                $label,
                $publishedOn,
                $effectiveFrom,
                $effectiveUntil,
                $command->replacesUuid,
                $fileName,
                $stored->fingerprint,
                $stored->key,
                $now,
            ));
        }

        $drafts = $this->chunker->chunk($extractor->extract($this->storage->path($stored->key)));
        foreach ($drafts as $index => $draft) {
            $this->passageRepository->add(new Passage(
                $this->idFactory->make(),
                $version,
                $index + 1,
                $draft->page,
                $draft->section,
                $draft->article,
                $draft->text,
                Passage::fingerprintOf($draft->page, $draft->section, $draft->article, $draft->text),
            ));
        }

        $this->runRepository->add(new IngestionRun(
            $this->idFactory->make(),
            $version,
            self::PIPELINE_VERSION,
            $extractor->name(),
            $this->chunker->name(),
            $this->chunker->parameters(),
            null,
            \count($drafts),
            $now,
        ));

        return $version;
    }

    private function extractorFor(string $fileName): TextExtractorInterface
    {
        foreach ($this->textExtractors as $extractor) {
            if ($extractor->supports($fileName)) {
                return $extractor;
            }
        }

        throw new UnsupportedDocumentFormatException($fileName);
    }

    private function date(?string $value): ?\DateTimeImmutable
    {
        return $value === null || trim($value) === '' ? null : new \DateTimeImmutable($value . ' 00:00:00');
    }
}
