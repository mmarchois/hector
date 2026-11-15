<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\User\User;

interface PasswordHasherInterface
{
    public function hash(User $user, string $plainPassword): string;

    public function isValid(User $user, string $plainPassword): bool;
}
