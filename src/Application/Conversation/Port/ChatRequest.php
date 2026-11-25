<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

final readonly class ChatRequest
{
    /**
     * @param list<ChatMessage>         $messages     alternés, le premier et le dernier de l'utilisateur
     * @param array<string, mixed>|null $outputSchema schéma JSON imposé à la réponse (sortie structurée)
     */
    public function __construct(
        public string $system,
        public array $messages,
        public ?array $outputSchema = null,
    ) {
    }
}
