<?php

declare(strict_types=1);

namespace App\Application\Conversation\Capability;

use App\Application\Conversation\Port\Participant;

/**
 * Ce qu'un contexte métier apporte à la conversation (docs/ARCHITECTURE.md, § 6). Le socle
 * ne sait rien du métier : il découvre les capacités (services étiquetés `app.capability`)
 * et leur demande, à chaque tour, leurs outils et leurs consignes pour cet interlocuteur.
 */
interface CapabilityInterface
{
    /** @return list<ToolInterface> outils offerts à cet interlocuteur pour ce tour */
    public function tools(Participant $participant): array;

    /** Texte ajouté aux consignes du tour ; null : rien à dire. */
    public function instructions(Participant $participant): ?string;
}
