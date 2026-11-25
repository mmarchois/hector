<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

final readonly class ChatResponse
{
    /**
     * @param list<ChatToolCall> $toolCalls       outils demandés : la réponse n'est pas finie
     * @param mixed              $providerContent contenu brut du message, à renvoyer tel quel
     *                                            (ChatMessage::$providerContent)
     */
    public function __construct(
        public string $text,
        public ChatStopReason $stopReason,
        /** Modèle qui a réellement répondu (un repli du fournisseur peut l'avoir changé) */
        public string $model,
        public ChatUsage $usage,
        public array $toolCalls = [],
        public mixed $providerContent = null,
    ) {
    }
}
