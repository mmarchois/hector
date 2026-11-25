<?php

declare(strict_types=1);

namespace App\Domain\Conversation\Exception;

/** Tour inconnu, ou d'une conversation d'un autre utilisateur (CV1). */
final class TurnNotFoundException extends \Exception
{
}
