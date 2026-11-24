<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\CommandInterface;

/**
 * Crée un compte complet ou, si l'auteur de la requête est un invité, convertit
 * ce compte invité (ID2).
 * Validation : config/validator/Application/User/Command/RegisterUserCommand.xml.
 */
final readonly class RegisterUserCommand implements CommandInterface
{
    public function __construct(
        public string $fullName,
        public string $email,
        public string $password,
        public bool $professional = false,
        public ?string $companyName = null,
        public ?string $siret = null,
        public ?string $vatNumber = null,
        // Obligatoire pour un nouveau compte ; un invité les a acceptées à l'ouverture de son compte
        public ?string $acceptedTermsOfUseVersion = null,
        // Utilisateur authentifié par la requête, s'il y en a un (pas un champ de la requête)
        public ?string $currentUserUuid = null,
    ) {
    }
}
