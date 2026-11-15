<?php

declare(strict_types=1);

namespace App\Application\User\Query;

use App\Application\QueryInterface;

final readonly class GetUserByUuidQuery implements QueryInterface
{
    public function __construct(
        public string $uuid,
    ) {
    }
}
