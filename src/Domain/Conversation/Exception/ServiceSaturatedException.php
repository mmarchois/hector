<?php

declare(strict_types=1);

namespace App\Domain\Conversation\Exception;

/**
 * CV9 : le budget global de jetons des dernières 24 heures est atteint. Le tour est refusé
 * avant tout appel au modèle, quel que soit son auteur.
 */
final class ServiceSaturatedException extends \Exception
{
}
