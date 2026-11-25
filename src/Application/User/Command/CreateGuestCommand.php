<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\CommandInterface;

/**
 * Ouvre un compte invité (décision D17).
 * Validation : config/validator/Application/User/Command/CreateGuestCommand.xml.
 */
final readonly class CreateGuestCommand implements CommandInterface
{
    public function __construct(
        public string $acceptedTermsOfUseVersion,
    ) {
    }
}
