<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Application\User\Port\UserOwnedDataInterface;
use App\Domain\Conversation\Repository\ConversationRepositoryInterface;

/**
 * Les conversations d'un utilisateur, vues du contexte User. Le pont vit dans
 * l'infrastructure : ni User ni Conversation ne dépendent l'un de l'autre (CV7).
 */
final readonly class ConversationUserOwnedData implements UserOwnedDataInterface
{
    public function __construct(
        private ConversationRepositoryInterface $conversationRepository,
    ) {
    }

    public function transfer(string $fromUserUuid, string $toUserUuid): void
    {
        $this->conversationRepository->transferOwnership($fromUserUuid, $toUserUuid);
    }

    public function erase(string $userUuid): void
    {
        $this->conversationRepository->removeAllByOwner($userUuid);
    }
}
