<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Query;

use App\Domain\Knowledge\Repository\DocumentVersionRepositoryInterface;
use App\Domain\Knowledge\Repository\SourceRepositoryInterface;
use App\Domain\Knowledge\Source;

final readonly class ListSourcesQueryHandler
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
        private DocumentVersionRepositoryInterface $versionRepository,
    ) {
    }

    /** @return list<SourceOverview> */
    public function __invoke(ListSourcesQuery $query): array
    {
        return array_map(
            fn (Source $source): SourceOverview => new SourceOverview($source, $this->versionRepository->findBySource($source)),
            $this->sourceRepository->findAllOrdered(),
        );
    }
}
