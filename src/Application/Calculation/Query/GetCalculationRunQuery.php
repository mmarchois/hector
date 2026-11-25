<?php

declare(strict_types=1);

namespace App\Application\Calculation\Query;

use App\Application\QueryInterface;

final readonly class GetCalculationRunQuery implements QueryInterface
{
    public function __construct(
        public string $runUuid,
        public string $ownerUuid,
    ) {
    }
}
