<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Knowledge\Dto;

use App\Domain\Knowledge\DocumentStatus;
use App\Domain\Knowledge\Passage;
use OpenApi\Attributes as OA;

/** Un passage cité, tel qu'on a le droit de le montrer (KN5). */
final readonly class PassageResponse implements \JsonSerializable
{
    public function __construct(
        public string $uuid,
        #[OA\Property(enum: ['regulatory', 'products'])]
        public string $corpus,
        public string $title,
        public string $publisher,
        #[OA\Property(description: 'Genre de la source : standard, regulation, guide, manufacturer_manual, datasheet, hector_sheet')]
        public string $kind,
        #[OA\Property(description: 'Libellé de la version citée')]
        public string $version,
        #[OA\Property(format: 'date', nullable: true)]
        public ?string $publishedOn,
        #[OA\Property(format: 'date', nullable: true)]
        public ?string $effectiveFrom,
        #[OA\Property(format: 'date', nullable: true)]
        public ?string $effectiveUntil,
        #[OA\Property(description: 'Vrai si la version a été retirée depuis la citation')]
        public bool $withdrawn,
        #[OA\Property(nullable: true)]
        public ?int $page,
        #[OA\Property(nullable: true)]
        public ?string $section,
        #[OA\Property(nullable: true)]
        public ?string $article,
        #[OA\Property(description: 'Extrait, selon la licence de la source ; null : simple renvoi au document', nullable: true)]
        public ?string $excerpt,
    ) {
    }

    public static function fromPassage(Passage $passage): self
    {
        $version = $passage->getVersion();
        $source = $version->getSource();

        return new self(
            uuid: $passage->getUuid(),
            corpus: $source->getCorpus()->value,
            title: $source->getTitle(),
            publisher: $source->getPublisher(),
            kind: $source->getKind()->value,
            version: $version->getLabel(),
            publishedOn: $version->getPublishedOn()?->format('Y-m-d'),
            effectiveFrom: $version->getEffectiveFrom()?->format('Y-m-d'),
            effectiveUntil: $version->getEffectiveUntil()?->format('Y-m-d'),
            withdrawn: $version->getStatus() === DocumentStatus::Withdrawn,
            page: $passage->getPage(),
            section: $passage->getSection(),
            article: $passage->getArticle(),
            excerpt: $passage->excerpt(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
