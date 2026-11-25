<?php

declare(strict_types=1);

namespace App\Domain\Knowledge;

/** Genre d'une source documentaire (docs/DOMAIN.md, § 4.2). */
enum SourceKind: string
{
    case Standard = 'standard';
    case Regulation = 'regulation';
    case Guide = 'guide';
    case ManufacturerManual = 'manufacturer_manual';
    case Datasheet = 'datasheet';
    case HectorSheet = 'hector_sheet';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Norme',
            self::Regulation => 'Texte réglementaire',
            self::Guide => 'Guide',
            self::ManufacturerManual => 'Notice de fabricant',
            self::Datasheet => 'Fiche technique',
            self::HectorSheet => 'Fiche Hector',
        };
    }
}
