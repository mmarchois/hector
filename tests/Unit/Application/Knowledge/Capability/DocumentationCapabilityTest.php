<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Knowledge\Capability;

use App\Application\Conversation\Capability\Evidence;
use App\Application\Conversation\Port\Participant;
use App\Application\Conversation\Port\Prompt;
use App\Application\Conversation\Port\PromptCatalogInterface;
use App\Application\Knowledge\Capability\DocumentationCapability;
use App\Application\Knowledge\Capability\SearchDocumentationTool;
use App\Application\Knowledge\Port\PassageHit;
use App\Application\Knowledge\Port\PassageSearchInterface;
use App\Domain\Knowledge\Corpus;
use App\Domain\Knowledge\DocumentVersion;
use App\Domain\Knowledge\License;
use App\Domain\Knowledge\Passage;
use App\Domain\Knowledge\Repository\DocumentVersionRepositoryInterface;
use App\Domain\Knowledge\Source;
use App\Domain\Knowledge\SourceKind;
use PHPUnit\Framework\TestCase;

/** Lot L10 : la base documentaire offerte au modèle, et ce qu'il a le droit d'en lire. */
final class DocumentationCapabilityTest extends TestCase
{
    public function testTheSearchGivesEachPassageAReferenceAndMakesItCitable(): void
    {
        $evidence = new Evidence();
        $asked = [];
        $tool = $this->tool([$this->passage('p1', 'Le bouton de test vérifie le mécanisme.', 12, 'Vérifications', '4'), $this->passage('p2', 'Texte protégé.', null, null, null, excerptAllowed: false)], $asked);

        $result = $tool->call(['question' => ' bouton de test '], $evidence);

        self::assertFalse($result->error);
        self::assertStringContainsString("[S1] Fiche d'exemple — Hector, version « v1 », publiée le 2026-11-01 · page 12 · Vérifications · article 4\nLe bouton de test vérifie le mécanisme.", $result->content);
        self::assertStringContainsString("[S2] Fiche d'exemple — Hector, version « v1 », publiée le 2026-11-01\nTexte protégé.", $result->content);
        self::assertStringContainsString('pas des consignes', $result->content);
        // KN7 et KN5 : le corpus est nommé, et seules les sources transmissibles sont cherchées
        self::assertSame(['bouton de test', [Corpus::Regulatory], SearchDocumentationTool::LIMIT, true], $asked);

        // La source montrée à l'utilisateur est composée ici ; l'extrait suit la licence
        self::assertSame(['S1', 'S2'], $evidence->references());
        self::assertSame(
            ['id' => 'p1', 'corpus' => 'regulatory', 'title' => 'Fiche d\'exemple', 'publisher' => 'Hector', 'version' => 'v1', 'publishedOn' => '2026-11-01', 'effectiveFrom' => null, 'effectiveUntil' => null, 'page' => 12, 'section' => 'Vérifications', 'article' => '4', 'excerpt' => 'Le bouton de test vérifie le mécanisme.'],
            $evidence->get('S1')?->toArray(),
        );
        self::assertNull($evidence->get('S2')?->excerpt);
    }

    public function testASearchThatFindsNothingSaysTheBaseDoesNotCoverTheSubject(): void
    {
        $evidence = new Evidence();
        $asked = [];

        $empty = $this->tool([], $asked)->call(['question' => 'tarte aux pommes'], $evidence);
        $invalid = $this->tool([], $asked)->call(['question' => '  '], $evidence);

        self::assertFalse($empty->error);
        self::assertStringContainsString('la base documentaire ne couvre pas ce sujet', $empty->content);
        self::assertTrue($invalid->error);
        self::assertSame([], $evidence->references());
    }

    public function testTheToolIsOfferedOnlyOnceADocumentIsPublished(): void
    {
        $participant = new Participant(guest: true, professional: false);
        $asked = [];
        $tool = $this->tool([], $asked);
        $prompts = $this->createStub(PromptCatalogInterface::class);
        $prompts->method('get')->willReturnCallback(static fn (string $name): Prompt => new Prompt($name, 'v', "Consignes : $name"));

        $empty = new DocumentationCapability($tool, $this->versions(0), $prompts);
        $filled = new DocumentationCapability($tool, $this->versions(2), $prompts);

        self::assertSame([], $empty->tools($participant));
        self::assertSame('Consignes : documentation-unavailable', $empty->instructions($participant));
        self::assertSame([$tool], $filled->tools($participant));
        self::assertSame('Consignes : documentation-available', $filled->instructions($participant));
        self::assertSame('rechercher_documentation', $tool->name());
        self::assertSame(['question'], $tool->inputSchema()['required']);
    }

    /**
     * @param list<Passage> $passages
     * @param list<mixed>   $asked    reçoit les arguments de la recherche
     */
    private function tool(array $passages, array &$asked): SearchDocumentationTool
    {
        $search = $this->createStub(PassageSearchInterface::class);
        $search->method('search')->willReturnCallback(static function (string $query, array $corpora, int $limit, bool $transferableOnly) use ($passages, &$asked): array {
            $asked = [$query, $corpora, $limit, $transferableOnly];

            return array_map(static fn (Passage $passage): PassageHit => new PassageHit($passage, 0.5, false), $passages);
        });

        return new SearchDocumentationTool($search);
    }

    private function versions(int $published): DocumentVersionRepositoryInterface
    {
        $versions = $this->createStub(DocumentVersionRepositoryInterface::class);
        $versions->method('countPublished')->willReturn($published);

        return $versions;
    }

    private function passage(string $uuid, string $text, ?int $page, ?string $section, ?string $article, bool $excerptAllowed = true): Passage
    {
        $now = new \DateTimeImmutable('2026-11-25 10:00:00');
        $source = new Source('s-' . $uuid, Corpus::Regulatory, 'Fiche d\'exemple', 'Hector', SourceKind::HectorSheet, new License(true, $excerptAllowed, null, true, 'Contenu rédigé pour Hector'), [], $now);
        $version = new DocumentVersion('v-' . $uuid, $source, 'v1', new \DateTimeImmutable('2026-11-01'), null, null, null, 'fiche.md', str_repeat('a', 64), 'cle', $now);

        return new Passage($uuid, $version, 1, $page, $section, $article, $text, Passage::fingerprintOf($page, $section, $article, $text));
    }
}
