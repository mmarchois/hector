<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Query;

use App\Domain\Knowledge\Exception\PassageNotFoundException;
use App\Domain\Knowledge\Passage;
use App\Domain\Knowledge\Repository\PassageRepositoryInterface;

final readonly class GetPassageQueryHandler
{
    public function __construct(
        private PassageRepositoryInterface $passageRepository,
    ) {
    }

    public function __invoke(GetPassageQuery $query): Passage
    {
        $passage = $this->passageRepository->findOneByUuid($query->passageUuid);
        // Un brouillon n'a été relu par personne : il n'existe pas pour un lecteur
        if (!$passage instanceof Passage || $passage->getVersion()->isDraft()) {
            throw new PassageNotFoundException();
        }

        return $passage;
    }
}
