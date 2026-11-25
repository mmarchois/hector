<?php

declare(strict_types=1);

namespace App\Application\Conversation\Query;

use App\Application\QueryInterface;

final readonly class GetTurnQuery implements QueryInterface
{
    public function __construct(
        public string $turnUuid,
        public string $ownerUuid,
    ) {
    }
}
