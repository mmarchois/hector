<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Auth\Dto;

use OpenApi\Attributes as OA;

/** Corps de POST /api/v1/auth/register, validé par RegisterUserCommand.xml (bus). */
final class RegisterRequest
{
    #[OA\Property(example: 'Marie Ohm')]
    public ?string $fullName = null;

    #[OA\Property(format: 'email', example: 'marie.ohm@exemple.fr')]
    public ?string $email = null;

    #[OA\Property(format: 'password', minLength: 8, example: 'motdepasse123')]
    public ?string $password = null;

    #[OA\Property(description: 'Compte professionnel : entreprise, SIRET et TVA deviennent obligatoires.')]
    public bool $professional = false;

    #[OA\Property(example: 'Ohm Élec')]
    public ?string $companyName = null;

    #[OA\Property(description: '14 chiffres', example: '12345678901234')]
    public ?string $siret = null;

    #[OA\Property(description: 'FR suivi de 11 chiffres', example: 'FR12345678901')]
    public ?string $vatNumber = null;

    #[OA\Property(description: 'Version des conditions d\'utilisation acceptées (GET /api/v1/terms-of-use). Inutile pour un invité, qui les a acceptées à l\'ouverture de son compte.', example: '2026-11-24')]
    public ?string $acceptedTermsOfUseVersion = null;
}
