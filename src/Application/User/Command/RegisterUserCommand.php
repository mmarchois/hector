<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\CommandInterface;

/** Validation : config/validator/Application/User/Command/RegisterUserCommand.xml. */
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
    ) {
    }
}
