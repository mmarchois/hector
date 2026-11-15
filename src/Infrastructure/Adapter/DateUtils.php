<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Domain\Port\DateUtilsInterface;

final class DateUtils implements DateUtilsInterface
{
    public function getNow(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
