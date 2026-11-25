<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

/** La version des conditions d'utilisation acceptée n'est pas la version en vigueur. */
final class TermsOfUseNotAcceptedException extends \Exception
{
}
