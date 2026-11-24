<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Erreur HTTP accompagnée d'un motif lisible par le client (`reason`), qui dit ce qui
 * débloque la suite : `guest_account` (créer un compte), plus tard `signup_required`.
 * Rendue par ApiExceptionListener : {code, reason, message}.
 */
final class ReasonHttpException extends HttpException
{
    public const string GUEST_ACCOUNT = 'guest_account';
    public const string TERMS_OF_USE_OUTDATED = 'terms_of_use_outdated';

    public function __construct(
        int $statusCode,
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($statusCode, $message);
    }
}
