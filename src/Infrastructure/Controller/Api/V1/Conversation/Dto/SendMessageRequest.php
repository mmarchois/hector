<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Conversation\Dto;

use OpenApi\Attributes as OA;

/** Validation : sur SendMessageCommand. */
final class SendMessageRequest
{
    #[OA\Property(maxLength: 4000, example: 'Un différentiel 30 mA déclenche de façon aléatoire chez un client.')]
    public ?string $text = null;
}
