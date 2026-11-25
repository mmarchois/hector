<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

use App\Domain\Knowledge\Exception\IndexingNotAllowedException;

/** Un document de référence, toutes versions confondues (docs/DOMAIN.md, § 4.2). */
class Source
{
    /**
     * @param list<string> $domains domaines couverts (habitat, tertiaire, IRVE…)
     */
    public function __construct(
        private string $uuid,
        private Corpus $corpus,
        private string $title,
        private string $publisher,
        private SourceKind $kind,
        private License $license,
        private array $domains,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getCorpus(): Corpus
    {
        return $this->corpus;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPublisher(): string
    {
        return $this->publisher;
    }

    public function getKind(): SourceKind
    {
        return $this->kind;
    }

    public function getLicense(): License
    {
        return $this->license;
    }

    /** @return list<string> */
    public function getDomains(): array
    {
        return $this->domains;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * KN5 : si l'indexation n'est pas permise, la source ne peut pas être ingérée.
     *
     * @throws IndexingNotAllowedException
     */
    public function assertIndexable(): void
    {
        if (!$this->license->indexingAllowed) {
            throw new IndexingNotAllowedException($this->title);
        }
    }
}
