<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Adapter\Knowledge;

use App\Application\Knowledge\Port\PassageHit;
use App\Application\Knowledge\Port\PassageSearchInterface;
use App\Domain\Knowledge\Corpus;
use App\Tests\Support\KnowledgeFixtures;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Lot L10 : recherche par mots dans un corpus de test (une fiche, une notice de produit). */
final class PostgresPassageSearchTest extends KernelTestCase
{
    use KnowledgeFixtures;

    private PassageSearchInterface $search;

    protected function setUp(): void
    {
        self::bootKernel();
        $search = static::getContainer()->get(PassageSearchInterface::class);
        self::assertInstanceOf(PassageSearchInterface::class, $search);
        $this->search = $search;
    }

    public function testAQuestionInEverydayWordsFindsTheRelevantPassage(): void
    {
        $this->document('fiche-exemple.md');

        $hits = $this->search->search('À quoi sert le bouton de test de mon différentiel ?', [Corpus::Regulatory]);

        self::assertNotSame([], $hits);
        self::assertStringContainsString('bouton de test', $hits[0]->passage->getText());
        // Le français est racinisé : « déclenche » retrouve « déclenchement »
        self::assertNotSame([], $this->search->search('il déclenche', [Corpus::Regulatory]));
    }

    public function testAnExactIdentifierBringsItsPassageFirst(): void
    {
        $this->document('notice-exemple.md', ['corpus' => 'products', 'kind' => 'manufacturer_manual']);

        $hits = $this->search->search('quel disjoncteur de la gamme est le REF-HX-4822 ?', [Corpus::Products]);

        // « disjoncteur » et « gamme » sont dans plusieurs passages : la référence exacte tranche
        self::assertGreaterThan(1, \count($hits));
        self::assertTrue($hits[0]->exactIdentifier);
        self::assertStringContainsString('REF-HX-4822', $hits[0]->passage->getText());
        self::assertFalse($hits[1]->exactIdentifier);
    }

    public function testKn7ASearchNamesItsCorporaAndEachHitCarriesItsOwn(): void
    {
        $this->document('fiche-exemple.md');
        $this->document('notice-exemple.md', ['corpus' => 'products']);

        $regulatory = $this->search->search('disjoncteur tableau', [Corpus::Regulatory]);
        $products = $this->search->search('disjoncteur tableau', [Corpus::Products]);
        $both = $this->search->search('disjoncteur tableau', [Corpus::Regulatory, Corpus::Products], limit: 20);

        self::assertSame([Corpus::Regulatory], $this->corporaOf($regulatory));
        self::assertSame([Corpus::Products], $this->corporaOf($products));
        self::assertEqualsCanonicalizing([Corpus::Regulatory, Corpus::Products], $this->corporaOf($both));
        self::assertSame([], $this->search->search('disjoncteur tableau', []));
    }

    public function testOnlyPublishedVersionsAreSearched(): void
    {
        $draft = $this->document('fiche-exemple.md', ['publish' => false]);
        self::assertSame([], $this->search->search('bouton de test', [Corpus::Regulatory]));

        $published = $this->document('fiche-exemple.md', ['title' => 'Fiche publiée']);
        self::assertNotSame([], $this->search->search('bouton de test', [Corpus::Regulatory]));

        // KN4 : une version retirée sort de la recherche
        $this->withdraw($published);
        self::assertSame([], $this->search->search('bouton de test', [Corpus::Regulatory]));
        self::assertTrue($draft->isDraft());
    }

    public function testKn5ASourceThatCannotBeSentToAThirdPartyIsLeftOutOnDemand(): void
    {
        $this->document('fiche-exemple.md', ['thirdParty' => false]);

        self::assertNotSame([], $this->search->search('bouton de test', [Corpus::Regulatory]));
        self::assertSame([], $this->search->search('bouton de test', [Corpus::Regulatory], transferableOnly: true));
    }

    public function testAQuestionOutsideTheCorpusOrMadeOfPunctuationFindsNothing(): void
    {
        $this->document('fiche-exemple.md');

        self::assertSame([], $this->search->search('recette de la tarte aux pommes', [Corpus::Regulatory]));
        self::assertSame([], $this->search->search(' & | ! ( ) :* \' " ; -- ', [Corpus::Regulatory]));
        // La ponctuation d'une question ne casse pas la recherche
        self::assertNotSame([], $this->search->search('différentiel & (bouton) | test:* \'; --', [Corpus::Regulatory]));
        self::assertCount(2, $this->search->search('différentiel bouton test courant fiche', [Corpus::Regulatory], limit: 2));
    }

    /**
     * @param list<PassageHit> $hits
     *
     * @return list<Corpus>
     */
    private function corporaOf(array $hits): array
    {
        self::assertNotSame([], $hits);

        return array_values(array_unique(array_map(static fn (PassageHit $hit): Corpus => $hit->passage->getVersion()->getSource()->getCorpus(), $hits), \SORT_REGULAR));
    }
}
