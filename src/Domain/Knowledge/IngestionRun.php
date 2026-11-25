<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

/**
 * Trace d'une ingestion : avec quelle chaîne de traitement les passages d'une version ont
 * été produits. Même fichier et même chaîne : mêmes passages (KN6).
 */
class IngestionRun
{
    /**
     * @param array<string, mixed> $chunkerParameters
     */
    public function __construct(
        private string $uuid,
        private DocumentVersion $version,
        private string $pipelineVersion,
        private string $extractor,
        private string $chunker,
        private array $chunkerParameters,
        /** Modèle de vecteurs et sa version (lot L12) */
        private ?string $embeddingModel,
        private int $passageCount,
        private \DateTimeImmutable $startedAt,
    ) {
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getVersion(): DocumentVersion
    {
        return $this->version;
    }

    public function getPipelineVersion(): string
    {
        return $this->pipelineVersion;
    }

    public function getExtractor(): string
    {
        return $this->extractor;
    }

    public function getChunker(): string
    {
        return $this->chunker;
    }

    /** @return array<string, mixed> */
    public function getChunkerParameters(): array
    {
        return $this->chunkerParameters;
    }

    public function getEmbeddingModel(): ?string
    {
        return $this->embeddingModel;
    }

    public function getPassageCount(): int
    {
        return $this->passageCount;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }
}
