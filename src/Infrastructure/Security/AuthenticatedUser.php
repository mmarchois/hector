<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Infrastructure\Security\User\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/** Utilisateur authentifié de la requête courante (JWT). */
final readonly class AuthenticatedUser
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function getUuid(): string
    {
        $user = $this->security->getUser();

        if (!$user instanceof SecurityUser) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return $user->getUuid();
    }
}
