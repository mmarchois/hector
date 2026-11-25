<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Adapter\Knowledge;

use App\Application\Knowledge\Port\ExtractedPage;
use App\Application\Knowledge\Port\PassageDraft;
use App\Infrastructure\Adapter\Knowledge\HeadingChunker;
use PHPUnit\Framework\TestCase;

/** Lot L9 : découpage en passages (KN3, KN6). */
final class HeadingChunkerTest extends TestCase
{
    public function testAPassageKeepsItsSectionAndItsArticle(): void
    {
        $passages = (new HeadingChunker())->chunk([new ExtractedPage(null, <<<'MD'
            Préambule sans titre.

            # Guide

            ## Protection

            Premier paragraphe.

            ### Article 4.2 — Différentiel

            Texte de l'article.

            ### Autre point

            Hors de l'article.

            ## Limites ##

            Fin.
            MD)]);

        self::assertEquals([
            new PassageDraft(null, null, null, 'Préambule sans titre.'),
            new PassageDraft(null, 'Guide > Protection', null, 'Premier paragraphe.'),
            new PassageDraft(null, 'Guide > Protection > Article 4.2 — Différentiel', '4.2', 'Texte de l\'article.'),
            // Un titre de même niveau referme l'article
            new PassageDraft(null, 'Guide > Protection > Autre point', null, 'Hors de l\'article.'),
            new PassageDraft(null, 'Guide > Limites', null, 'Fin.'),
        ], $passages);
    }

    public function testATableIsNeverCutEvenBeyondTheTargetSize(): void
    {
        $rows = implode("\n", array_map(static fn (int $i): string => \sprintf('| Ligne %d | %s |', $i, str_repeat('x', 40)), range(1, 40)));
        $table = "| Geste | Remarque |\n|---|---|\n" . $rows;
        self::assertGreaterThan(HeadingChunker::MAX_CHARACTERS, mb_strlen($table));

        $passages = (new HeadingChunker())->chunk([new ExtractedPage(null, "## Tableau\n\nAvant.\n\n$table\n\nAprès.")]);

        self::assertSame(['Avant.', $table, 'Après.'], array_map(static fn (PassageDraft $passage): string => $passage->text, $passages));
    }

    public function testParagraphsAreGroupedUpToTheTargetSizeWithoutBeingCut(): void
    {
        $paragraph = str_repeat('mot ', 100) . 'fin.';
        $text = implode("\n\n", array_fill(0, 7, $paragraph));

        $passages = (new HeadingChunker())->chunk([new ExtractedPage(null, $text)]);

        // 404 caractères par paragraphe : deux par passage, pas trois
        self::assertCount(4, $passages);
        self::assertSame("$paragraph\n\n$paragraph", $passages[0]->text);
        self::assertSame($paragraph, $passages[3]->text);
        foreach ($passages as $passage) {
            self::assertLessThanOrEqual(HeadingChunker::MAX_CHARACTERS, mb_strlen($passage->text));
        }
    }

    public function testAPassageNeverSpansTwoPagesAndTitlesCarryOver(): void
    {
        $passages = (new HeadingChunker())->chunk([
            new ExtractedPage(1, "## Rôle\n\nDébut, page un."),
            new ExtractedPage(2, 'Suite, page deux.'),
            new ExtractedPage(3, ''),
            new ExtractedPage(4, "## Limites\n\nPage quatre."),
        ]);

        self::assertEquals([
            new PassageDraft(1, 'Rôle', null, 'Début, page un.'),
            new PassageDraft(2, 'Rôle', null, 'Suite, page deux.'),
            new PassageDraft(4, 'Limites', null, 'Page quatre.'),
        ], $passages);
    }

    public function testKn6TheSamePagesGiveTheSamePassages(): void
    {
        $pages = [new ExtractedPage(1, "# Titre\n\nUn.\n\nDeux.\r\n\r\n## Sous-titre\r\n\r\nTrois.")];
        $chunker = new HeadingChunker();

        self::assertEquals($chunker->chunk($pages), $chunker->chunk($pages));
        self::assertSame('heading@1', $chunker->name());
        self::assertSame(['maxCharacters' => HeadingChunker::MAX_CHARACTERS], $chunker->parameters());
        // Les fins de ligne Windows ne changent rien au découpage
        self::assertSame('Trois.', $chunker->chunk($pages)[1]->text);
    }
}
