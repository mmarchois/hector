<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

/**
 * L'interlocuteur d'un tour, vu du serveur. Le modèle n'en reçoit que le type de compte et la
 * langue : aucune donnée d'identité ne part chez son fournisseur.
 */
final readonly class Participant
{
    public function __construct(
        public bool $guest,
        public bool $professional,
        /**
         * Compte au nom duquel un outil enregistre ce qu'il fait (un calcul). Jamais transmis
         * au modèle. Null hors de l'application (évaluation) : rien n'est alors enregistré
         */
        public ?string $uuid = null,
        /** Langue de son application : celle de ce que le serveur compose pour lui (décision D19) */
        public string $locale = 'fr',
    ) {
    }

    public function speaking(string $locale): self
    {
        return new self($this->guest, $this->professional, $this->uuid, $locale);
    }
}
