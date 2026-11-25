<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

/** Délai dépassé, réseau, surcharge ou limite de débit du fournisseur : passager. */
final class ChatModelUnavailableException extends ChatModelException
{
}
