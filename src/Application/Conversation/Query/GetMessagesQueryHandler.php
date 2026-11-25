<?php

declare(strict_types=1);

namespace App\Application\Conversation\Query;

use App\Domain\Conversation\Conversation;
use App\Domain\Conversation\Exception\ConversationNotFoundException;
use App\Domain\Conversation\Repository\ConversationRepositoryInterface;
use App\Domain\Conversation\Repository\MessageRepositoryInterface;

final readonly class GetMessagesQueryHandler
{
    public function __construct(
        private ConversationRepositoryInterface $conversationRepository,
        private MessageRepositoryInterface $messageRepository,
    ) {
    }

    public function __invoke(GetMessagesQuery $query): MessagePage
    {
        $conversation = $this->conversationRepository->findOneByUuid($query->conversationUuid);
        if (!$conversation instanceof Conversation || !$conversation->isOwnedBy($query->ownerUuid)) {
            throw new ConversationNotFoundException();
        }

        $messages = $this->messageRepository->findPage($conversation, $query->before, $query->limit);
        $hasMore = $messages !== [] && $this->messageRepository->existsBefore($conversation, $messages[0]->getUuid());

        return new MessagePage($messages, $hasMore);
    }
}
