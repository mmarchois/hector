<?php

declare(strict_types=1);

namespace App\Domain\Conversation;

enum TurnFailure: string
{
    /** Indisponibilité passagère : relancer a des chances d'aboutir */
    case Unavailable = 'unavailable';
    /** Erreur qui ne disparaîtra pas en relançant (configuration, requête refusée) */
    case Error = 'error';
}
