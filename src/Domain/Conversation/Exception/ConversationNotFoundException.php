<?php

declare(strict_types=1);

namespace App\Domain\Conversation\Exception;

/** Conversation inconnue, ou d'un autre utilisateur (CV1 : rien ne trahit son existence). */
final class ConversationNotFoundException extends \Exception
{
}
