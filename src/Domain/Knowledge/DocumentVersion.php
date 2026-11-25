<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

use App\Domain\Knowledge\Exception\DocumentVersionImmutableException;
use App\Domain\Knowledge\Exception\DocumentVersionNotPublishableException;

/**
 * Une édition précise et datée d'une source (docs/DOMAIN.md, § 4.2). Elle naît en
 * brouillon à l'ingestion, n'est publiée qu'après relecture humaine (KN1), et ne change
 * plus ensuite (KN2) : corriger, c'est publier une nouvelle version.
 */
class DocumentVersion
{
    private DocumentStatus $status = DocumentStatus::Draft;
    private ?string $reviewedBy = null;
    private ?\DateTimeImmutable $reviewedAt = null;
    private ?\DateTimeImmutable $withdrawnAt = null;

    public function __construct(
        private string $uuid,
        private Source $source,
        /** Libellé de version donné par l'éditeur : « édition 2024 », « v2 »… */
        private string $label,
        /** Date de publication du document par son éditeur */
        private ?\DateTimeImmutable $publishedOn,
        private ?\DateTimeImmutable $effectiveFrom,
        private ?\DateTimeImmutable $effectiveUntil,
        /** Version que celle-ci remplace */
        private ?string $replacesUuid,
        private string $fileName,
        /** Empreinte SHA-256 du fichier d'origine */
        private string $fileFingerprint,
        /** Où le fichier d'origine est conservé (FileStorageInterface) */
        private string $storageKey,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getPublishedOn(): ?\DateTimeImmutable
    {
        return $this->publishedOn;
    }

    public function getEffectiveFrom(): ?\DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function getEffectiveUntil(): ?\DateTimeImmutable
    {
        return $this->effectiveUntil;
    }

    public function getReplacesUuid(): ?string
    {
        return $this->replacesUuid;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getFileFingerprint(): string
    {
        return $this->fileFingerprint;
    }

    public function getStorageKey(): string
    {
        return $this->storageKey;
    }

    public function getStatus(): DocumentStatus
    {
        return $this->status;
    }

    public function getReviewedBy(): ?string
    {
        return $this->reviewedBy;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function getWithdrawnAt(): ?\DateTimeImmutable
    {
        return $this->withdrawnAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Nouvelle ingestion d'un brouillon : le fichier et ses dates peuvent encore changer.
     *
     * @throws DocumentVersionImmutableException
     */
    public function reingest(
        string $fileName,
        string $fileFingerprint,
        string $storageKey,
        ?\DateTimeImmutable $publishedOn,
        ?\DateTimeImmutable $effectiveFrom,
        ?\DateTimeImmutable $effectiveUntil,
        ?string $replacesUuid,
    ): void {
        $this->assertDraft();
        $this->fileName = $fileName;
        $this->fileFingerprint = $fileFingerprint;
        $this->storageKey = $storageKey;
        $this->publishedOn = $publishedOn;
        $this->effectiveFrom = $effectiveFrom;
        $this->effectiveUntil = $effectiveUntil;
        $this->replacesUuid = $replacesUuid;
    }

    /**
     * KN1 : pas de publication sans éditeur, libellé de version, date de publication, licence
     * renseignée et validation humaine.
     *
     * @throws DocumentVersionImmutableException
     * @throws DocumentVersionNotPublishableException
     */
    public function publish(string $reviewer, \DateTimeImmutable $now): void
    {
        $this->assertDraft();

        $missing = [];
        if (trim($this->source->getPublisher()) === '') {
            $missing[] = 'éditeur';
        }
        if (trim($this->label) === '') {
            $missing[] = 'libellé de version';
        }
        if ($this->publishedOn === null) {
            $missing[] = 'date de publication';
        }
        if (!$this->source->getLicense()->documented()) {
            $missing[] = 'licence';
        }
        if (trim($reviewer) === '') {
            $missing[] = 'relecteur';
        }
        if ($missing !== []) {
            throw new DocumentVersionNotPublishableException($missing);
        }

        $this->status = DocumentStatus::Published;
        $this->reviewedBy = trim($reviewer);
        $this->reviewedAt = $now;
    }

    /**
     * KN4 : une version retirée sort de la recherche, ses passages restent consultables.
     *
     * @throws DocumentVersionImmutableException
     */
    public function withdraw(\DateTimeImmutable $now): void
    {
        if ($this->status !== DocumentStatus::Published) {
            throw new DocumentVersionImmutableException('Seule une version publiée peut être retirée.');
        }

        $this->status = DocumentStatus::Withdrawn;
        $this->withdrawnAt = $now;
    }

    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    /** KN2 : une version publiée ne change plus. */
    private function assertDraft(): void
    {
        if ($this->status !== DocumentStatus::Draft) {
            throw new DocumentVersionImmutableException('Une version publiée ou retirée ne change plus : publiez une nouvelle version.');
        }
    }
}
