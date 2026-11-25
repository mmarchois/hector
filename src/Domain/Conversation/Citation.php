<?php

declare(strict_types=1);

namespace App\Domain\Conversation;

/**
 * Source citée par un énoncé, telle que le serveur la compose à partir de ce qui a été lu
 * pendant le tour (décision D7) : le modèle ne fournit que l'identifiant. Le socle n'en
 * connaît pas le contenu : c'est la capacité qui a lu la source qui la décrit (CV7).
 */
final readonly class Citation
{
    public function __construct(
        /** Identifiant durable de ce qui est cité (un passage ; plus tard, une exécution de calcul) */
        public string $id,
        public string $corpus,
        public string $title,
        public string $publisher,
        /** Libellé de la version citée */
        public string $version,
        public ?string $publishedOn,
        public ?string $effectiveFrom,
        public ?string $effectiveUntil,
        public ?int $page,
        public ?string $section,
        public ?string $article,
        /** Extrait affichable selon la licence ; null : simple renvoi au document */
        public ?string $excerpt,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            corpus: $data['corpus'],
            title: $data['title'],
            publisher: $data['publisher'],
            version: $data['version'],
            publishedOn: $data['publishedOn'] ?? null,
            effectiveFrom: $data['effectiveFrom'] ?? null,
            effectiveUntil: $data['effectiveUntil'] ?? null,
            page: $data['page'] ?? null,
            section: $data['section'] ?? null,
            article: $data['article'] ?? null,
            excerpt: $data['excerpt'] ?? null,
        );
    }
}
