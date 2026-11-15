<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\CommandInterface;

/** Validation : config/validator/Application/User/Command/SaveProfileCommand.xml. */
final readonly class SaveProfileCommand implements CommandInterface
{
    public function __construct(
        public string $userUuid,
        public string $fullName,
        public string $email,
        public bool $professional = false,
        public ?string $companyName = null,
        public ?string $siret = null,
        public ?string $vatNumber = null,
        /** Requis uniquement pour changer de mot de passe. */
        public ?string $currentPassword = null,
        /** null ou vide = mot de passe inchangé. */
        public ?string $newPassword = null,
    ) {
    }
}
