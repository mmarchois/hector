<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

final readonly class ChatMessage
{
    /**
     * @param list<ChatToolCall>   $toolCalls       outils demandés par ce message de l'assistant
     * @param list<ChatToolResult> $toolResults     résultats rendus par ce message de l'utilisateur
     * @param mixed                $providerContent contenu d'un message de l'assistant tel que le
     *                                              fournisseur l'a rendu, à lui renvoyer intact dans la
     *                                              suite du même tour (son raisonnement en fait partie).
     *                                              Opaque : seul l'adaptateur sait ce qu'il contient
     */
    public function __construct(
        public ChatRole $role,
        public string $text,
        public array $toolCalls = [],
        public array $toolResults = [],
        public mixed $providerContent = null,
    ) {
    }
}
