<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

/** La session présentée n'est pas celle d'un compte invité valide. */
final class InvalidGuestSessionException extends \Exception
{
}
