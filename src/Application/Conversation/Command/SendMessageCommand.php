<?php

declare(strict_types=1);

namespace App\Application\Conversation\Command;

use App\Application\CommandInterface;

/**
 * Enregistre le message de l'utilisateur et ouvre son tour, statut « en cours ».
 * Validation : config/validator/Application/Conversation/Command/SendMessageCommand.xml.
 */
final readonly class SendMessageCommand implements CommandInterface
{
    public function __construct(
        public string $conversationUuid,
        public string $ownerUuid,
        public string $text,
    ) {
    }
}
