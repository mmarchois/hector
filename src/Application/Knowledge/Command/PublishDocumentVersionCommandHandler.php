<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Command;

use App\Domain\Knowledge\DocumentVersion;
use App\Domain\Knowledge\Exception\DocumentVersionNotFoundException;
use App\Domain\Knowledge\Repository\DocumentVersionRepositoryInterface;
use App\Domain\Port\DateUtilsInterface;

final readonly class PublishDocumentVersionCommandHandler
{
    public function __construct(
        private DocumentVersionRepositoryInterface $versionRepository,
        private DateUtilsInterface $dateUtils,
    ) {
    }

    public function __invoke(PublishDocumentVersionCommand $command): DocumentVersion
    {
        $version = $this->versionRepository->findOneByUuid($command->versionUuid);
        if (!$version instanceof DocumentVersion) {
            throw new DocumentVersionNotFoundException();
        }

        $version->publish($command->reviewer, $this->dateUtils->getNow());

        return $version;
    }
}
