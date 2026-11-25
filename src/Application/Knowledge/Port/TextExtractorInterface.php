<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Port;

/**
 * Extraction du texte d'un document (décision D14). Une extraction fausse produit des
 * citations fausses avec toutes les apparences du vrai (docs/SECURITY.md, risque T2) :
 * chaque méthode est choisie sur essai, et une version n'est publiée qu'après relecture.
 */
interface TextExtractorInterface
{
    /** Nom et version de la méthode, consignés dans la trace d'ingestion (KN6). */
    public function name(): string;

    public function supports(string $fileName): bool;

    /** @return list<ExtractedPage> dans l'ordre du document */
    public function extract(string $path): array;
}
