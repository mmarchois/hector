<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Application\StringUtilsInterface;

final class StringUtils implements StringUtilsInterface
{
    public function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
