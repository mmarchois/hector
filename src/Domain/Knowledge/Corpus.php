<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

/**
 * Ensemble de sources géré selon ses propres règles (décision D16). Une source appartient
 * à un seul corpus, et chaque résultat de recherche garde le sien jusqu'à l'affichage (KN7).
 */
enum Corpus: string
{
    /** Textes, normes, guides et fiches Hector */
    case Regulatory = 'regulatory';
    /** Notices et fiches techniques de fabricants (phase 6) */
    case Products = 'products';

    public function label(): string
    {
        return match ($this) {
            self::Regulatory => 'Réglementaire et technique',
            self::Products => 'Produits',
        };
    }
}
