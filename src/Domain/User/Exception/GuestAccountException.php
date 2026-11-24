<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

/** Opération réservée à un compte complet, refusée à un invité (ID1). */
final class GuestAccountException extends \Exception
{
}
