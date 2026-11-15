<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Auth\Dto;

use OpenApi\Attributes as OA;

/** Corps de PUT /api/v1/auth/me, validé par SaveProfileCommand.xml (bus). */
final class UpdateProfileRequest
{
    #[OA\Property(example: 'Marie Ohm')]
    public ?string $fullName = null;

    #[OA\Property(format: 'email', example: 'marie.ohm@exemple.fr')]
    public ?string $email = null;

    #[OA\Property(description: 'Compte professionnel : entreprise, SIRET et TVA deviennent obligatoires.')]
    public bool $professional = false;

    #[OA\Property(example: 'Ohm Élec')]
    public ?string $companyName = null;

    #[OA\Property(description: '14 chiffres', example: '12345678901234')]
    public ?string $siret = null;

    #[OA\Property(description: 'FR suivi de 11 chiffres', example: 'FR12345678901')]
    public ?string $vatNumber = null;

    #[OA\Property(description: 'Requis uniquement pour changer de mot de passe.', format: 'password')]
    public ?string $currentPassword = null;

    #[OA\Property(description: 'Absent ou vide : mot de passe inchangé.', format: 'password', minLength: 8)]
    public ?string $newPassword = null;
}
