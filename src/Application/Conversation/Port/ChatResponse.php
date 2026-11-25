<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

final readonly class ChatResponse
{
    public function __construct(
        public string $text,
        public ChatStopReason $stopReason,
        /** Modèle qui a réellement répondu (un repli du fournisseur peut l'avoir changé) */
        public string $model,
        public ChatUsage $usage,
    ) {
    }
}
