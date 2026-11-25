<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

final readonly class ChatToolCall
{
    /**
     * @param array<string, mixed> $input
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $input,
    ) {
    }
}
