<?php

declare(strict_types=1);

namespace App\Domain\Conversation\Repository;

use App\Domain\Conversation\Turn;

interface TurnRepositoryInterface
{
    public function add(Turn $turn): Turn;

    public function findOneByUuid(string $uuid): ?Turn;
}
