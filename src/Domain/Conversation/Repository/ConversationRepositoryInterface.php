<?php

declare(strict_types=1);

namespace App\Domain\Conversation\Repository;

use App\Domain\Conversation\Conversation;

interface ConversationRepositoryInterface
{
    public function add(Conversation $conversation): Conversation;

    public function findOneByUuid(string $uuid): ?Conversation;

    /**
     * Conversations d'un utilisateur, la plus récemment active d'abord.
     *
     * @return list<Conversation>
     */
    public function findPageByOwner(string $ownerUuid, int $page, int $perPage): array;

    public function countByOwner(string $ownerUuid): int;

    /** Rattache toutes les conversations d'un utilisateur à un autre (invité qui rejoint son compte). */
    public function transferOwnership(string $fromOwnerUuid, string $toOwnerUuid): void;

    /** Supprime les conversations d'un utilisateur, avec leurs messages et leurs tours. */
    public function removeAllByOwner(string $ownerUuid): void;
}
