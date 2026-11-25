<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

/** Requête rejetée par le fournisseur (clé invalide, requête mal formée…) : relancer n'y change rien. */
final class ChatModelFailedException extends ChatModelException
{
}
