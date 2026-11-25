<?php

declare(strict_types=1);

namespace App\Application\Conversation\Query;

use App\Domain\Conversation\Repository\ConversationRepositoryInterface;

final readonly class ListConversationsQueryHandler
{
    public function __construct(
        private ConversationRepositoryInterface $conversationRepository,
    ) {
    }

    public function __invoke(ListConversationsQuery $query): ConversationPage
    {
        return new ConversationPage(
            $this->conversationRepository->findPageByOwner($query->ownerUuid, $query->page, ListConversationsQuery::PER_PAGE),
            $query->page,
            ListConversationsQuery::PER_PAGE,
            $this->conversationRepository->countByOwner($query->ownerUuid),
        );
    }
}
