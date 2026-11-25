<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

/**
 * Jeton de rafraîchissement JWT (application mobile). Détail technique de la
 * session, hors du domaine : il hérite du modèle gesdinet (id, refreshToken,
 * username, valid). `username` contient l'uuid de l'utilisateur
 * (cf. SecurityUser::getUserIdentifier()).
 */
class RefreshToken extends BaseRefreshToken
{
}
