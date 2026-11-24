<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

/**
 * Ce que le socle a besoin de savoir de l'auteur d'un message : son type de compte, jamais
 * son identité (aucun nom, email ni SIRET ne part chez le fournisseur du modèle).
 */
interface ParticipantInterface
{
    public function profileOf(string $userUuid): Participant;
}
