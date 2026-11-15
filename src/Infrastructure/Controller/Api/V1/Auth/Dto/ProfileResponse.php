<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Auth\Dto;

use App\Domain\User\User;

/** Profil de l'utilisateur authentifié (register, GET/PUT /api/v1/auth/me). */
final readonly class ProfileResponse implements \JsonSerializable
{
    /**
     * @param string[] $roles
     */
    public function __construct(
        public string $uuid,
        public string $email,
        public string $fullName,
        public array $roles,
        public bool $professional,
        public ?string $companyName,
        public ?string $siret,
        public ?string $vatNumber,
    ) {
    }

    public static function fromUser(User $user): self
    {
        $company = $user->getCompany();

        return new self(
            uuid: $user->getUuid(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            roles: $user->getRoles(),
            professional: $user->isProfessional(),
            companyName: $company?->getName(),
            siret: $company?->getSiret(),
            vatNumber: $company?->getVatNumber(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
