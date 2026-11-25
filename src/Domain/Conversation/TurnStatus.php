<?php

declare(strict_types=1);

namespace App\Domain\Conversation;

enum TurnStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    /** Réponse de repli, explicite, après un brouillon rejeté (lot L7) */
    case Degraded = 'degraded';
    case Failed = 'failed';

    public function isFinished(): bool
    {
        return $this !== self::InProgress;
    }
}
