<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Conversation\Service;

use App\Application\Conversation\Service\AnswerFormat;
use App\Domain\Conversation\Block;
use App\Domain\Conversation\DraftValidator;
use App\Domain\Conversation\StatementKind;
use App\Tests\Support\FakeChatModel;
use PHPUnit\Framework\TestCase;

/** Lot L7 : du brouillon JSON du modèle aux blocs du message. */
final class AnswerFormatTest extends TestCase
{
    public function testEachItemBecomesABlock(): void
    {
        $blocks = AnswerFormat::parse(FakeChatModel::draft([
            ['warning', 'none', ' Éloignez-vous de l\'appareil. '],
            ['statement', 'hypothesis', 'Un défaut d\'isolement est possible.'],
            ['statement', 'sourced_fact', 'Un dispositif 30 mA est exigé.', ['p1']],
            ['statement', 'reported_opinion', 'Gamme jugée fiable.', [], 'Revue des installateurs'],
            ['question', 'none', 'Depuis quand ?'],
        ]));

        self::assertEquals([
            Block::warning('Éloignez-vous de l\'appareil.'),
            Block::statement(StatementKind::Hypothesis, 'Un défaut d\'isolement est possible.'),
            Block::statement(StatementKind::SourcedFact, 'Un dispositif 30 mA est exigé.', ['p1']),
            Block::statement(StatementKind::ReportedOpinion, 'Gamme jugée fiable.', source: 'Revue des installateurs'),
            Block::question('Depuis quand ?'),
        ], $blocks);
        // Une source vide n'est pas enregistrée
        self::assertArrayNotHasKey('source', $blocks[1]->toArray());
    }

    public function testTheFallbackTextKeepsTheNatureReadable(): void
    {
        $blocks = AnswerFormat::parse(FakeChatModel::draft([
            ['statement', 'hypothesis', 'Un défaut d\'isolement est possible.'],
            ['warning', 'none', 'Ne touchez pas le tableau.'],
        ]));

        // CV5 : une application qui ne connaît pas ces blocs affiche quand même leur nature
        self::assertSame('Hypothèse : Un défaut d\'isolement est possible.', $blocks[0]->fallbackText);
        self::assertSame('Attention : Ne touchez pas le tableau.', $blocks[1]->fallbackText);
    }

    public function testAMalformedItemBecomesABlockTheCheckRejects(): void
    {
        $blocks = AnswerFormat::parse('{"blocks": [{"type": "statement", "kind": "none", "text": "Sans nature"}, {"type": "table", "text": "Inconnu"}, "texte nu"]}');

        self::assertCount(3, $blocks);
        self::assertCount(3, DraftValidator::check($blocks, []));
    }

    public function testADraftWithoutBlocksIsEmpty(): void
    {
        self::assertSame([], AnswerFormat::parse('{"réponse": "Bonjour"}'));
        self::assertSame([], AnswerFormat::parse('"Bonjour"'));
    }

    public function testTextThatIsNotJsonIsAnError(): void
    {
        $this->expectException(\JsonException::class);

        AnswerFormat::parse('Bonjour, voici ma réponse.');
    }

    public function testTheSchemaListsEveryStatementKind(): void
    {
        $item = AnswerFormat::schema()['properties']['blocks']['items'];

        self::assertSame(['statement', 'question', 'warning'], $item['properties']['type']['enum']);
        self::assertSame(
            [...array_map(static fn (StatementKind $kind): string => $kind->value, StatementKind::cases()), 'none'],
            $item['properties']['kind']['enum'],
        );
        // Sortie structurée : tous les champs requis, aucun champ libre
        self::assertSame(['type', 'kind', 'text', 'citations', 'source'], $item['required']);
        self::assertFalse($item['additionalProperties']);
    }
}
