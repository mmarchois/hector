<?php

declare(strict_types=1);

namespace App\Application\Conversation\Query;

use App\Domain\Conversation\Message;

final readonly class MessagePage
{
    /**
     * @param list<Message> $items   du plus ancien au plus récent
     * @param bool          $hasMore des messages plus anciens existent (page suivante : before = uuid du premier)
     */
    public function __construct(
        public array $items,
        public bool $hasMore,
    ) {
    }
}
