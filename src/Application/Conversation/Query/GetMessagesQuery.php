<?php

declare(strict_types=1);

namespace App\Application\Conversation\Query;

use App\Application\QueryInterface;

/**
 * Derniers messages d'une conversation, ou ceux qui précèdent $before.
 * Validation : config/validator/Application/Conversation/Query/GetMessagesQuery.xml.
 */
final readonly class GetMessagesQuery implements QueryInterface
{
    public const int DEFAULT_LIMIT = 30;

    public function __construct(
        public string $conversationUuid,
        public string $ownerUuid,
        public ?string $before = null,
        public int $limit = self::DEFAULT_LIMIT,
    ) {
    }
}
