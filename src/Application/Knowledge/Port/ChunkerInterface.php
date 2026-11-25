<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Port;

/** Découpage d'un texte extrait en passages (décision D14). Déterministe : mêmes pages, mêmes passages (KN6). */
interface ChunkerInterface
{
    /** Nom et version de la méthode, consignés dans la trace d'ingestion. */
    public function name(): string;

    /** @return array<string, mixed> réglages consignés dans la trace d'ingestion */
    public function parameters(): array;

    /**
     * @param list<ExtractedPage> $pages
     *
     * @return list<PassageDraft> dans l'ordre du document
     */
    public function chunk(array $pages): array;
}
