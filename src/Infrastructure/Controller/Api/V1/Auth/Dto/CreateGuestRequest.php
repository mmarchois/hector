<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Auth\Dto;

use OpenApi\Attributes as OA;

/** Ouverture d'un compte invité. Validation : sur CreateGuestCommand. */
final class CreateGuestRequest
{
    #[OA\Property(description: 'Version des conditions d\'utilisation acceptées (GET /api/v1/terms-of-use)', example: '2026-11-24')]
    public ?string $acceptedTermsOfUseVersion = null;
}
