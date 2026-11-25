<?php

declare(strict_types=1);

namespace App\Domain\Conversation;

/**
 * Nature d'un énoncé de l'assistant (glossaire, docs/DOMAIN.md, § 3) : ce qui permet de
 * distinguer faits, hypothèses et recommandations, et de contrôler chacun selon sa règle (CV4).
 */
enum StatementKind: string
{
    /** Appuyé sur un passage lu pendant le tour : au moins une citation */
    case SourcedFact = 'sourced_fact';
    /** Ce que l'utilisateur a dit ou observé */
    case DeclaredFact = 'declared_fact';
    /** Issu d'une exécution de calcul du tour : au moins une citation de cette exécution */
    case CalculationResult = 'calculation_result';
    /** Connaissance générale du modèle, sans source : affichée comme telle */
    case GeneralExplanation = 'general_explanation';
    case Hypothesis = 'hypothesis';
    case Recommendation = 'recommendation';
    case MissingInformation = 'missing_information';
    /** Avis ou retour d'expérience d'une source nommée */
    case ReportedOpinion = 'reported_opinion';

    /** Intitulé du texte de repli (CV5) : la nature reste lisible dans une ancienne application. */
    public function label(): string
    {
        return match ($this) {
            self::SourcedFact => 'Fait sourcé',
            self::DeclaredFact => 'Ce que vous avez indiqué',
            self::CalculationResult => 'Résultat de calcul',
            self::GeneralExplanation => 'Explication générale, non sourcée',
            self::Hypothesis => 'Hypothèse',
            self::Recommendation => 'Recommandation',
            self::MissingInformation => 'Information manquante',
            self::ReportedOpinion => 'Avis rapporté',
        };
    }

    /** Une nature qui n'existe que par sa preuve : passage ou exécution de calcul (CV4). */
    public function requiresCitation(): bool
    {
        return $this === self::SourcedFact || $this === self::CalculationResult;
    }
}
