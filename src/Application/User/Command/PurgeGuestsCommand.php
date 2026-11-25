<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\CommandInterface;

/**
 * Supprime les invités qui n'ont plus de session (ID4). Une session vit 30 jours après
 * son dernier renouvellement : un invité est donc supprimé après 30 jours sans activité.
 */
final readonly class PurgeGuestsCommand implements CommandInterface
{
}
