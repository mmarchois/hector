<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

final readonly class ChatRequest
{
    /**
     * @param list<ChatMessage>         $messages     alternés, le premier et le dernier de l'utilisateur
     * @param array<string, mixed>|null $outputSchema schéma JSON imposé à la réponse (sortie structurée)
     * @param list<ChatTool>            $tools        outils que le modèle peut demander
     */
    public function __construct(
        public string $system,
        public array $messages,
        public ?array $outputSchema = null,
        public array $tools = [],
    ) {
    }
}
