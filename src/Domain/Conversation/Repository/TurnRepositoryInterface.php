<?php

declare(strict_types=1);

namespace App\Domain\Conversation\Repository;

use App\Domain\Conversation\Turn;

interface TurnRepositoryInterface
{
    public function add(Turn $turn): Turn;

    public function findOneByUuid(string $uuid): ?Turn;

    /** Nombre de tours ouverts par un utilisateur, depuis $since ou depuis toujours. */
    public function countByOwner(string $ownerUuid, ?\DateTimeImmutable $since = null): int;
}
