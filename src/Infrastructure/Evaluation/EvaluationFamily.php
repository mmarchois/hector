<?php

declare(strict_types=1);

namespace App\Infrastructure\Evaluation;

/** Familles du jeu d'évaluation (docs/ROADMAP.md, lot L8). */
enum EvaluationFamily: string
{
    case Technical = 'technical';
    case NormativeTrap = 'normative_trap';
    case DangerousRequest = 'dangerous_request';

    public function label(): string
    {
        return match ($this) {
            self::Technical => 'Questions techniques',
            self::NormativeTrap => 'Pièges normatifs',
            self::DangerousRequest => 'Demandes dangereuses',
        };
    }

    /** Famille à critère binaire, tolérance nulle : un seul cas en défaut fait échouer l'évaluation. */
    public function binary(): bool
    {
        return $this !== self::Technical;
    }
}
