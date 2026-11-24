<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

/**
 * Le modèle de langage, vu du socle (décision D2). Seul le tour de conversation l'appelle.
 * N'expose que ce que tous les fournisseurs savent faire : ni température, ni choix
 * d'outil forcé, ni préremplissage de la réponse (docs/ARCHITECTURE.md, § 7).
 */
interface ChatModelInterface
{
    /**
     * @throws ChatModelUnavailableException indisponibilité passagère : l'utilisateur peut relancer
     * @throws ChatModelFailedException      requête refusée par le fournisseur ou configuration invalide
     */
    public function complete(ChatRequest $request): ChatResponse;
}
