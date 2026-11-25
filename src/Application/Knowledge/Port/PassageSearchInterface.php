<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Port;

use App\Domain\Knowledge\Corpus;

/**
 * Recherche de passages dans des corpus nommés (KN7). Seules les versions publiées sont
 * cherchées : ni brouillon, ni version retirée (KN4). Premier adaptateur : plein texte de
 * PostgreSQL (décision D4) ; la recherche par vecteurs s'y ajoutera au lot L12.
 */
interface PassageSearchInterface
{
    /**
     * @param list<Corpus> $corpora          corpus interrogés ; aucun : aucun résultat
     * @param bool         $transferableOnly écarte les sources dont la licence interdit la
     *                                       transmission à un sous-traitant (KN5) : à vrai dès
     *                                       que les passages partent chez le fournisseur du modèle
     *
     * @return list<PassageHit> du plus pertinent au moins pertinent
     */
    public function search(string $query, array $corpora, int $limit = 6, bool $transferableOnly = false): array;
}
