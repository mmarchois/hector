<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Command;

use App\Domain\Knowledge\Corpus;
use App\Domain\Knowledge\License;
use App\Domain\Knowledge\Repository\SourceRepositoryInterface;
use App\Domain\Knowledge\Source;
use App\Domain\Knowledge\SourceKind;
use App\Domain\Port\DateUtilsInterface;
use App\Domain\Port\IdFactoryInterface;

final readonly class CreateSourceCommandHandler
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
        private IdFactoryInterface $idFactory,
        private DateUtilsInterface $dateUtils,
    ) {
    }

    public function __invoke(CreateSourceCommand $command): Source
    {
        return $this->sourceRepository->add(new Source(
            $this->idFactory->make(),
            Corpus::from($command->corpus),
            trim($command->title),
            trim($command->publisher),
            SourceKind::from($command->kind),
            new License(
                $command->indexingAllowed,
                $command->excerptAllowed,
                $command->excerptMaxCharacters,
                $command->thirdPartyTransferAllowed,
                trim($command->licenseOrigin),
            ),
            array_values(array_filter(array_map(trim(...), $command->domains), static fn (string $domain): bool => $domain !== '')),
            $this->dateUtils->getNow(),
        ));
    }
}
