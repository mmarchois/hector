<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Erreur 422 rattachée à des champs de la requête, rendue par ApiExceptionListener
 * au même format que les violations de validation des commandes.
 */
final class ViolationHttpException extends UnprocessableEntityHttpException
{
    /**
     * @param array<string, string> $violations champ de la requête => message
     */
    public function __construct(
        public readonly array $violations,
    ) {
        parent::__construct('Validation failed.');
    }
}
