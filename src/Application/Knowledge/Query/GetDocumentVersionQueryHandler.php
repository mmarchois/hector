<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Query;

use App\Domain\Knowledge\DocumentVersion;
use App\Domain\Knowledge\Exception\DocumentVersionNotFoundException;
use App\Domain\Knowledge\Repository\DocumentVersionRepositoryInterface;
use App\Domain\Knowledge\Repository\IngestionRunRepositoryInterface;
use App\Domain\Knowledge\Repository\PassageRepositoryInterface;

final readonly class GetDocumentVersionQueryHandler
{
    public function __construct(
        private DocumentVersionRepositoryInterface $versionRepository,
        private PassageRepositoryInterface $passageRepository,
        private IngestionRunRepositoryInterface $runRepository,
    ) {
    }

    public function __invoke(GetDocumentVersionQuery $query): DocumentVersionDetail
    {
        $version = $this->versionRepository->findOneByUuid($query->versionUuid);
        if (!$version instanceof DocumentVersion) {
            throw new DocumentVersionNotFoundException();
        }

        return new DocumentVersionDetail(
            $version,
            $this->passageRepository->findByVersion($version),
            $this->runRepository->findLatestByVersion($version),
        );
    }
}
