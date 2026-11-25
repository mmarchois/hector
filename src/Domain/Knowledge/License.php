<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

/**
 * Ce qu'on a le droit de faire d'une source (KN5). Elle gouverne l'ingestion, l'affichage
 * d'un extrait et l'envoi d'un passage à un sous-traitant, dont le fournisseur du modèle.
 */
final readonly class License
{
    public function __construct(
        /** Faux : la source ne peut pas être ingérée */
        public bool $indexingAllowed,
        /** Faux : une citation renvoie au document sans le reproduire */
        public bool $excerptAllowed,
        /** Longueur maximale d'un extrait affiché ; null : pas de limite fixée */
        public ?int $excerptMaxCharacters,
        /** Faux : aucun passage ne part chez un sous-traitant */
        public bool $thirdPartyTransferAllowed,
        /** D'où vient l'autorisation : licence ouverte, accord écrit, contenu rédigé pour Hector… */
        public string $origin,
    ) {
    }

    /** Une licence sans origine n'est pas renseignée : la version ne peut pas être publiée (KN1). */
    public function documented(): bool
    {
        return trim($this->origin) !== '';
    }
}
