<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\CommandInterface;

/**
 * Un invité se connecte à un compte existant : ses données rejoignent ce compte, puis le
 * compte invité est supprimé (question P11). Le jeton de rafraîchissement de l'invité
 * prouve que la requête vient bien de lui.
 * Validation : config/validator/Application/User/Command/MergeGuestCommand.xml.
 */
final readonly class MergeGuestCommand implements CommandInterface
{
    public function __construct(
        public string $accountUuid,
        public string $guestRefreshToken,
    ) {
    }
}
