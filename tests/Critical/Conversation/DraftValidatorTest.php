<?php

declare(strict_types=1);

namespace App\Tests\Critical\Conversation;

use App\Domain\Conversation\Block;
use App\Domain\Conversation\DraftValidator;
use App\Domain\Conversation\StatementKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Contrôle déterministe d'un brouillon (lots L7 et L14) : pour chaque règle, au moins un cas
 * accepté et un cas rejeté. « p1 » est la seule source lue pendant le tour, « C1 » le seul
 * calcul exécuté.
 */
final class DraftValidatorTest extends TestCase
{
    private const array EVIDENCE = ['p1'];
    private const array CALCULATIONS = ['C1'];

    /** @return iterable<string, array{list<Block>}> */
    public static function acceptedDrafts(): iterable
    {
        yield 'explication générale' => [[Block::statement(StatementKind::GeneralExplanation, 'Un différentiel compare les courants entrant et sortant.')]];
        yield 'hypothèse, recommandation, question' => [[
            Block::statement(StatementKind::Hypothesis, 'Un appareil défectueux peut être en cause.'),
            Block::statement(StatementKind::Recommendation, 'Débranchez les appareils un à un.'),
            Block::question('Le déclenchement suit-il la mise en route d\'un appareil ?'),
        ]];
        yield 'fait déclaré et information manquante' => [[
            Block::statement(StatementKind::DeclaredFact, 'Vous indiquez que le différentiel saute la nuit.'),
            Block::statement(StatementKind::MissingInformation, 'Je ne connais pas l\'âge de l\'installation.'),
        ]];
        yield 'mise en garde' => [[Block::warning('Éloignez-vous de l\'appareil qui fume.')]];
        // CV4 : fait sourcé appuyé sur une source lue
        yield 'fait sourcé avec citation lue' => [[Block::statement(StatementKind::SourcedFact, 'Un dispositif 30 mA est exigé.', ['p1'])]];
        // Lot L14 : un résultat de calcul cite le calcul exécuté et n'en écrit aucun chiffre
        yield 'résultat de calcul qui cite son calcul' => [[Block::statement(StatementKind::CalculationResult, 'Courant absorbé par le chauffe-eau.', ['C1'])]];
        yield 'calcul présenté parmi d\'autres énoncés' => [[
            Block::statement(StatementKind::DeclaredFact, 'Vous indiquez un chauffe-eau de 3 kW alimenté en 230 V.'),
            Block::statement(StatementKind::CalculationResult, 'Courant absorbé par le chauffe-eau.', ['C1']),
            Block::statement(StatementKind::SourcedFact, 'Un dispositif 30 mA est exigé.', ['p1']),
        ]];
        // Un renvoi précis est permis quand il est appuyé sur une source lue
        yield 'renvoi précis avec citation lue' => [[Block::statement(StatementKind::SourcedFact, 'L\'article 411.3.3 exige un dispositif 30 mA.', ['p1'])]];
        yield 'avis rapporté avec sa source' => [[Block::statement(StatementKind::ReportedOpinion, 'Cette gamme est jugée fiable.', source: 'Revue des installateurs, mars 2026')]];
        yield 'nom d\'une norme sans disposition' => [[Block::statement(StatementKind::GeneralExplanation, 'La NF C 15-100 encadre les installations basse tension.')]];
        yield 'section d\'un conducteur' => [[Block::statement(StatementKind::Recommendation, 'Prévoyez un câble de section 2,5 mm².')]];
    }

    /** @return iterable<string, array{list<Block>, string}> */
    public static function rejectedDrafts(): iterable
    {
        yield 'aucun bloc' => [[], 'aucun bloc'];
        yield 'texte vide' => [[Block::question('   ')], 'texte vide'];
        yield 'type inconnu' => [[Block::fromArray(['type' => 'table', 'version' => 1, 'text' => 'Texte', 'fallbackText' => 'Texte'])], 'inconnu'];
        yield 'énoncé sans nature' => [[Block::fromArray(['type' => 'statement', 'version' => 1, 'text' => 'Texte', 'fallbackText' => 'Texte'])], 'doit avoir une nature'];
        // CV4
        yield 'fait sourcé sans citation' => [[Block::statement(StatementKind::SourcedFact, 'Un dispositif 30 mA est exigé.')], 'doit citer une source'];
        // Lot L14 : aucun nombre présenté comme un résultat de calcul ne vient du modèle
        yield 'résultat de calcul sans exécution' => [[Block::statement(StatementKind::CalculationResult, 'La chute de tension est de 2,1 %.')], 'cite un calcul exécuté pendant ce tour'];
        yield 'résultat de calcul sans chiffre mais sans exécution' => [[Block::statement(StatementKind::CalculationResult, 'Courant absorbé par le chauffe-eau.')], 'cite un calcul exécuté pendant ce tour'];
        yield 'résultat de calcul appuyé sur une source' => [[Block::statement(StatementKind::CalculationResult, 'Courant absorbé par le chauffe-eau.', ['p1'])], 'cite un calcul exécuté pendant ce tour'];
        yield 'résultat de calcul qui cite aussi une source' => [[Block::statement(StatementKind::CalculationResult, 'Courant absorbé par le chauffe-eau.', ['C1', 'p1'])], 'et lui seul'];
        yield 'résultat de calcul d\'un calcul inventé' => [[Block::statement(StatementKind::CalculationResult, 'Courant absorbé par le chauffe-eau.', ['C9'])], 'cite C9'];
        yield 'résultat de calcul qui écrit un chiffre' => [[Block::statement(StatementKind::CalculationResult, 'Le courant absorbé est de 14,5 A.', ['C1'])], 'ne contient aucun chiffre'];
        yield 'hypothèse qui cite un calcul' => [[Block::statement(StatementKind::Hypothesis, 'Le circuit est peut-être trop chargé.', ['C1'])], 'seul un énoncé « Résultat de calcul » cite un calcul (C1)'];
        yield 'fait sourcé appuyé sur un calcul' => [[Block::statement(StatementKind::SourcedFact, 'Un dispositif adapté est exigé.', ['C1'])], 'doit citer une source'];
        yield 'bloc de calcul écrit par le modèle' => [[Block::composed('calculation', ['calculation' => ['result' => ['value' => 16]]], 'Résultat : I = 16 A')], 'type « calculation » inconnu'];
        yield 'refus de calcul écrit par le modèle' => [[Block::composed('calculation_refusal', ['reasons' => ['Aucun.']], 'Calcul non effectué')], 'type « calculation_refusal » inconnu'];
        yield 'citation d\'une source non lue' => [[Block::statement(StatementKind::SourcedFact, 'Un dispositif 30 mA est exigé.', ['p9'])], 'cite p9'];
        yield 'citation inconnue sur une hypothèse' => [[Block::statement(StatementKind::Hypothesis, 'C\'est peut-être un défaut d\'isolement.', ['inventé'])], 'cite inventé'];
        yield 'avis rapporté sans source' => [[Block::statement(StatementKind::ReportedOpinion, 'Cette gamme est jugée fiable.')], 'doit nommer sa source'];
        // Renvoi précis sans citation valide
        yield 'article sans citation' => [[Block::statement(StatementKind::GeneralExplanation, 'L\'article 411.3.3 exige un dispositif 30 mA.')], 'disposition précise (« article 411.3.3 »)'];
        yield 'tableau dans une question' => [[Block::question('Avez-vous consulté le tableau 52H ?')], 'disposition précise (« tableau 52H »)'];
        yield 'paragraphe dans une mise en garde' => [[Block::warning('Respectez le § 7.1.')], 'disposition précise'];
        yield 'renvoi avec citation non lue' => [[Block::statement(StatementKind::SourcedFact, 'Voir l\'article 12.', ['p9'])], 'disposition précise (« article 12 »)'];
    }

    /** @param list<Block> $blocks */
    #[DataProvider('acceptedDrafts')]
    public function testAValidDraftIsAccepted(array $blocks): void
    {
        self::assertSame([], DraftValidator::check($blocks, self::EVIDENCE, self::CALCULATIONS));
    }

    /** @param list<Block> $blocks */
    #[DataProvider('rejectedDrafts')]
    public function testAnInvalidDraftIsRejectedWithItsReason(array $blocks, string $expectedReason): void
    {
        $reasons = DraftValidator::check($blocks, self::EVIDENCE, self::CALCULATIONS);

        self::assertNotSame([], $reasons);
        self::assertStringContainsString($expectedReason, implode("\n", $reasons));
    }

    public function testWithoutAnyCalculationRunNoCalculationResultCanPass(): void
    {
        $result = [Block::statement(StatementKind::CalculationResult, 'Courant absorbé par le chauffe-eau.', ['C1'])];

        self::assertSame([], DraftValidator::check($result, self::EVIDENCE, self::CALCULATIONS));
        self::assertNotSame([], DraftValidator::check($result, self::EVIDENCE));
        // Un calcul n'est pas une source : il ne rend citable aucun fait
        self::assertNotSame([], DraftValidator::check($result, evidence: ['C1']));
    }

    public function testEveryFaultyBlockIsReportedWithItsPosition(): void
    {
        $reasons = DraftValidator::check([
            Block::statement(StatementKind::Hypothesis, 'Un appareil défectueux peut être en cause.'),
            Block::statement(StatementKind::SourcedFact, 'Un dispositif 30 mA est exigé.'),
            Block::statement(StatementKind::GeneralExplanation, 'Voir le tableau 52H.'),
        ], self::EVIDENCE);

        self::assertCount(2, $reasons);
        self::assertStringStartsWith('Bloc 2 :', $reasons[0]);
        self::assertStringStartsWith('Bloc 3 :', $reasons[1]);
    }

    public function testWithoutAnySourceReadNoSourcedFactCanPass(): void
    {
        // État du lot L7 : aucune base documentaire, donc aucun fait sourcé possible
        self::assertNotSame([], DraftValidator::check([Block::statement(StatementKind::SourcedFact, 'Un dispositif 30 mA est exigé.', ['p1'])], evidence: []));
    }
}
