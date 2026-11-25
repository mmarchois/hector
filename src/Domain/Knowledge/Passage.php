<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

/**
 * Extrait localisé d'une version : unité de recherche et de citation. Immuable et
 * identifié (KN3) : une citation vise un passage, donc une version exacte.
 */
class Passage
{
    public function __construct(
        private string $uuid,
        private DocumentVersion $version,
        /** Ordre dans le document, à partir de 1 */
        private int $position,
        private ?int $page,
        /** Titres de section, du plus général au plus précis */
        private ?string $section,
        private ?string $article,
        private string $text,
        /** Empreinte de la localisation et du texte (KN6) */
        private string $fingerprint,
    ) {
    }

    /** Même localisation et même texte : même empreinte, d'une ingestion à l'autre (KN6). */
    public static function fingerprintOf(?int $page, ?string $section, ?string $article, string $text): string
    {
        return hash('sha256', json_encode([$page, $section, $article, $text], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getVersion(): DocumentVersion
    {
        return $this->version;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getPage(): ?int
    {
        return $this->page;
    }

    public function getSection(): ?string
    {
        return $this->section;
    }

    public function getArticle(): ?string
    {
        return $this->article;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    /**
     * KN5 : ce qu'on a le droit de montrer du passage. Null si la licence de la source
     * interdit tout extrait : la citation renvoie alors au document sans le reproduire.
     */
    public function excerpt(): ?string
    {
        $license = $this->version->getSource()->getLicense();
        if (!$license->excerptAllowed) {
            return null;
        }

        $max = $license->excerptMaxCharacters;

        return $max === null || mb_strlen($this->text) <= $max ? $this->text : rtrim(mb_substr($this->text, 0, $max)) . '…';
    }
}
