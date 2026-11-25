<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Query;

use App\Application\QueryInterface;

/** Un passage cité, pour le consulter : publié, ou retiré depuis (KN4). Jamais un brouillon. */
final readonly class GetPassageQuery implements QueryInterface
{
    public function __construct(
        public string $passageUuid,
    ) {
    }
}
