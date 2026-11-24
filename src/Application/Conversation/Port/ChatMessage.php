<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

final readonly class ChatMessage
{
    public function __construct(
        public ChatRole $role,
        public string $text,
    ) {
    }
}
