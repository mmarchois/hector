<?php

declare(strict_types=1);

namespace App\Application\Conversation\Command;

use App\Application\CommandInterface;

final readonly class CreateConversationCommand implements CommandInterface
{
    public function __construct(
        public string $ownerUuid,
    ) {
    }
}
