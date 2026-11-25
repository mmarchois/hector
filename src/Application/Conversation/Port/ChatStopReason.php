<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

enum ChatStopReason: string
{
    case EndTurn = 'end_turn';
    /** Réponse coupée à la longueur maximale */
    case MaxTokens = 'max_tokens';
    /** Le fournisseur refuse de répondre */
    case Refusal = 'refusal';
    case Other = 'other';
}
