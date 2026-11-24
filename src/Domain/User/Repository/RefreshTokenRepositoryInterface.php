<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

interface RefreshTokenRepositoryInterface
{
    /**
     * Révoque toutes les sessions mobiles d'un utilisateur : un refresh token volé
     * ne doit pas survivre à un changement de mot de passe ni à la suppression du compte.
     */
    public function removeAllByUser(string $userUuid): void;

    /** Uuid de l'utilisateur à qui appartient ce jeton, s'il est encore valide ; sinon null. */
    public function findUserUuidOfValidToken(string $refreshToken): ?string;
}
