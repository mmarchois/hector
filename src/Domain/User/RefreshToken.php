<?php

declare(strict_types=1);

namespace App\Domain\User;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

/**
 * Jeton de rafraîchissement JWT (application mobile).
 * Les champs (id, refreshToken, username, valid) viennent du modèle gesdinet ;
 * `username` contient l'uuid de l'utilisateur (cf. SecurityUser::getUserIdentifier()).
 */
class RefreshToken extends BaseRefreshToken
{
}
