<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

final readonly class ChatToolResult
{
    public function __construct(
        public string $toolCallId,
        public string $content,
        public bool $isError = false,
    ) {
    }
}
