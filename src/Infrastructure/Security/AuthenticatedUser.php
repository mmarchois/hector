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
        return $this->findUuid() ?? throw new UnauthorizedHttpException('Bearer');
    }

    /** Pour une route publique : uuid de l'auteur s'il a envoyé un jeton valide, sinon null. */
    public function findUuid(): ?string
    {
        $user = $this->security->getUser();

        return $user instanceof SecurityUser ? $user->getUuid() : null;
    }
}
