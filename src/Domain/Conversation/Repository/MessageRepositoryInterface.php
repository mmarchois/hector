<?php

declare(strict_types=1);

namespace App\Domain\Conversation\Repository;

use App\Domain\Conversation\Conversation;
use App\Domain\Conversation\Message;

interface MessageRepositoryInterface
{
    public function add(Message $message): Message;

    /**
     * Messages d'une conversation, du plus ancien au plus récent : les $limit derniers,
     * ou les $limit qui précèdent le message $beforeUuid. Les uuid sont croissants dans
     * le temps (IdFactoryInterface) : ils donnent l'ordre des messages.
     *
     * @return list<Message>
     */
    public function findPage(Conversation $conversation, ?string $beforeUuid, int $limit): array;

    /** Y a-t-il des messages plus anciens que $uuid dans la conversation ? */
    public function existsBefore(Conversation $conversation, string $uuid): bool;
}
