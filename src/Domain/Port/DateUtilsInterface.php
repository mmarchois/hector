<?php

declare(strict_types=1);

namespace App\Domain\Port;

interface DateUtilsInterface
{
    public function getNow(): \DateTimeImmutable;
}
