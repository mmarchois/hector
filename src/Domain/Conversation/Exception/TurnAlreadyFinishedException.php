<?php

declare(strict_types=1);

namespace App\Domain\Conversation\Exception;

/** Un tour terminé ne reçoit plus de réponse (CV3). */
final class TurnAlreadyFinishedException extends \Exception
{
}
