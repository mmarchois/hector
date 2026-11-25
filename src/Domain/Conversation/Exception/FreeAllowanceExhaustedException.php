<?php

declare(strict_types=1);

namespace App\Domain\Conversation\Exception;

/** CV8 : l'allocation gratuite de l'auteur est épuisée ; le tour est refusé avant tout appel au modèle. */
final class FreeAllowanceExhaustedException extends \Exception
{
    public function __construct(
        /** Invité : créer un compte débloque la suite. Sinon, attendre (plus tard : s'abonner). */
        public readonly bool $signupRequired,
    ) {
        parent::__construct();
    }
}
