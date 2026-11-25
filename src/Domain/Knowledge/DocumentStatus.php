<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

enum DocumentStatus: string
{
    /** Ingérée, à relire : invisible de la recherche */
    case Draft = 'draft';
    /** Relue et publiée : ne change plus (KN2) */
    case Published = 'published';
    /** Sortie de la recherche, encore consultable par les citations déjà émises (KN4) */
    case Withdrawn = 'withdrawn';
}
