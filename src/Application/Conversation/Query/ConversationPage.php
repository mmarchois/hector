<?php

declare(strict_types=1);

namespace App\Application\Conversation\Query;

use App\Domain\Conversation\Conversation;

final readonly class ConversationPage
{
    /**
     * @param list<Conversation> $items
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
    ) {
    }
}
