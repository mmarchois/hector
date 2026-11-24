<?php

declare(strict_types=1);

namespace App\Application\Conversation\Query;

use App\Application\QueryInterface;

/** Validation : config/validator/Application/Conversation/Query/ListConversationsQuery.xml. */
final readonly class ListConversationsQuery implements QueryInterface
{
    public const int PER_PAGE = 20;

    public function __construct(
        public string $ownerUuid,
        public int $page = 1,
    ) {
    }
}
