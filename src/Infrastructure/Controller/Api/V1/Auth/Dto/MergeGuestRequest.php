<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Auth\Dto;

use OpenApi\Attributes as OA;

/** Validation : sur MergeGuestCommand. */
final class MergeGuestRequest
{
    #[OA\Property(description: 'Jeton de rafraîchissement du compte invité, gardé par l\'application avant la connexion')]
    public ?string $guestRefreshToken = null;
}
