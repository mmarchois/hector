<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Console;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Lot L9 : la base documentaire pilotée par ses commandes, sur les fichiers de knowledge/
 * (une fiche à tableau, un texte paginé).
 */
final class KnowledgeConsoleCommandTest extends KernelTestCase
{
    private const string FIXTURES = __DIR__ . '/knowledge';

    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testADocumentIsIngestedReviewedPublishedThenWithdrawn(): void
    {
        $source = $this->createSource('Fiche différentiel');

        // Ingestion : un brouillon, à relire
        $ingested = $this->console('app:knowledge:ingest', ['source' => $source, 'file' => self::FIXTURES . '/fiche-exemple.md', '--label' => 'v1']);
        self::assertSame(0, $ingested->getStatusCode(), $ingested->getDisplay());
        $version = $this->uuidIn($ingested->getDisplay());

        // Relecture : chaque passage avec sa section, le tableau entier, l'article repéré (KN3)
        $shown = $this->console('app:knowledge:show', ['version' => $version])->getDisplay();
        self::assertStringContainsString('brouillon, à relire', $shown);
        self::assertStringContainsString('markdown@1, heading@1', $shown);
        self::assertStringContainsString('| Ouvrir le tableau | Électricien | Jamais par l\'usager |', $shown);
        $passages = $this->passages($version);
        self::assertSame([
            'Fiche d\'exemple : dispositif différentiel',
            'Fiche d\'exemple : dispositif différentiel > Rôle',
            'Fiche d\'exemple : dispositif différentiel > Vérifications',
            'Fiche d\'exemple : dispositif différentiel > Vérifications > Article 1 — Bouton de test',
            'Fiche d\'exemple : dispositif différentiel > Limites',
        ], array_column($passages, 'section'));
        self::assertSame([null, null, null, '1', null], array_column($passages, 'article'));
        self::assertSame(5, substr_count($passages[2]['text'], "\n") + 1);

        // KN1 : pas de publication sans date de publication ni relecteur
        $refused = $this->console('app:knowledge:publish', ['version' => $version]);
        self::assertSame(1, $refused->getStatusCode());
        self::assertStringContainsString('il manque : date de publication, relecteur', $this->flat($refused));

        // Le brouillon se réingère sous le même libellé, avec sa date
        $again = $this->console('app:knowledge:ingest', ['source' => $source, 'file' => self::FIXTURES . '/fiche-exemple.md', '--label' => 'v1', '--published-on' => '2026-11-01']);
        self::assertSame($version, $this->uuidIn($again->getDisplay()));
        // KN6 : même fichier, même chaîne, mêmes passages
        self::assertSame(array_column($passages, 'fingerprint'), array_column($this->passages($version), 'fingerprint'));

        $published = $this->console('app:knowledge:publish', ['version' => $version, '--reviewer' => 'A. Expert']);
        self::assertSame(0, $published->getStatusCode(), $published->getDisplay());
        self::assertStringContainsString('relue par A. Expert', $this->flat($published));

        // KN2 : une version publiée ne change plus
        $frozen = $this->console('app:knowledge:ingest', ['source' => $source, 'file' => self::FIXTURES . '/texte-pagine.txt', '--label' => 'v1']);
        self::assertSame(1, $frozen->getStatusCode());
        self::assertStringContainsString('ne change plus', $this->flat($frozen));
        self::assertSame(array_column($passages, 'fingerprint'), array_column($this->passages($version), 'fingerprint'));

        // KN4 : retirée, ses passages restent
        self::assertSame(0, $this->console('app:knowledge:withdraw', ['version' => $version])->getStatusCode());
        self::assertCount(5, $this->passages($version));
        $overview = $this->flat($this->console('app:knowledge:show'));
        self::assertStringContainsString('Fiche différentiel — Hector (Réglementaire et technique, Fiche Hector)', $overview);
        self::assertStringContainsString('retirée le', $overview);
    }

    public function testKn6TheSameFileGivesTheSamePassagesInAnotherVersion(): void
    {
        $source = $this->createSource('Fiche différentiel');
        $first = $this->ingest($source, 'fiche-exemple.md', 'v1');
        $second = $this->ingest($source, 'fiche-exemple.md', 'v2');

        self::assertNotSame($first, $second);
        self::assertSame(array_column($this->passages($first), 'fingerprint'), array_column($this->passages($second), 'fingerprint'));
        self::assertSame(
            $this->connection()->fetchOne('SELECT file_fingerprint FROM knowledge_document_version WHERE uuid = ?', [$first]),
            $this->connection()->fetchOne('SELECT file_fingerprint FROM knowledge_document_version WHERE uuid = ?', [$second]),
        );
    }

    public function testKn3APaginatedTextKeepsThePageOfEachPassage(): void
    {
        $version = $this->ingest($this->createSource('Texte paginé'), 'texte-pagine.txt', 'édition 2026');

        // La troisième page est vide : la suivante reste la quatrième
        self::assertSame([1, 2, 4], array_column($this->passages($version), 'page'));
        self::assertStringContainsString('text@1', $this->console('app:knowledge:show', ['version' => $version])->getDisplay());
    }

    public function testKn5ASourceWhoseLicenseForbidsIndexingIsNotIngested(): void
    {
        $source = $this->createSource('Norme protégée', ['--no-indexing' => true, '--kind' => 'standard', '--publisher' => 'Éditeur de normes']);

        $refused = $this->console('app:knowledge:ingest', ['source' => $source, 'file' => self::FIXTURES . '/fiche-exemple.md', '--label' => 'édition 2024']);

        self::assertSame(1, $refused->getStatusCode());
        self::assertStringContainsString('n\'en permet pas l\'indexation', $this->flat($refused));
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM knowledge_document_version WHERE source_uuid = ?', [$source]));
        self::assertStringContainsString('indexation interdite par la licence', $this->flat($this->console('app:knowledge:show')));
    }

    public function testKn7ASourceBelongsToACorpusAndCarriesItsLicense(): void
    {
        $source = $this->createSource('Notice de disjoncteur', ['--corpus' => 'products', '--kind' => 'manufacturer_manual', '--publisher' => 'Fabricant', '--no-third-party' => true, '--excerpt-max' => 300, '--domain' => ['habitat', 'tertiaire']]);

        $row = $this->connection()->fetchAssociative('SELECT * FROM knowledge_source WHERE uuid = ?', [$source]);
        self::assertIsArray($row);
        self::assertSame('products', $row['corpus']);
        self::assertSame('manufacturer_manual', $row['kind']);
        self::assertTrue($row['license_indexing_allowed']);
        self::assertFalse($row['license_third_party_transfer_allowed']);
        self::assertSame(300, $row['license_excerpt_max_characters']);
        self::assertSame(['habitat', 'tertiaire'], json_decode((string) $row['domains'], true));
    }

    public function testInvalidInputIsExplainedToTheOperator(): void
    {
        // Une source sans éditeur ni origine de licence
        $source = $this->console('app:knowledge:source:create', ['title' => 'Sans éditeur', '--corpus' => 'inconnu']);
        self::assertSame(2, $source->getStatusCode());
        self::assertStringContainsString('Corpus inconnu', $this->flat($source));
        self::assertStringContainsString('Indiquez l\'éditeur de la source.', $this->flat($source));
        self::assertStringContainsString('Indiquez d\'où vient le droit d\'utiliser cette source.', $this->flat($source));

        $valid = $this->createSource('Fiche');
        $badDate = $this->console('app:knowledge:ingest', ['source' => $valid, 'file' => self::FIXTURES . '/fiche-exemple.md', '--label' => 'v1', '--published-on' => '01/11/2026']);
        self::assertSame(2, $badDate->getStatusCode());
        self::assertStringContainsString('AAAA-MM-JJ', $this->flat($badDate));

        $unreadable = $this->console('app:knowledge:ingest', ['source' => $valid, 'file' => self::FIXTURES . '/tableur.csv', '--label' => 'v1']);
        self::assertSame(1, $unreadable->getStatusCode());
        self::assertStringContainsString('Aucune extraction ne sait lire « tableur.csv »', $this->flat($unreadable));

        $unknown = $this->console('app:knowledge:publish', ['version' => '0b507871-8b5e-4575-b297-a630310fc06e', '--reviewer' => 'A. Expert']);
        self::assertSame(1, $unknown->getStatusCode());
        self::assertStringContainsString('Version de document introuvable.', $this->flat($unknown));
    }

    /** @param array<string, mixed> $options */
    private function createSource(string $title, array $options = []): string
    {
        $tester = $this->console('app:knowledge:source:create', ['title' => $title, '--publisher' => 'Hector', '--license-origin' => 'Contenu rédigé pour Hector', ...$options]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        return $this->uuidIn($tester->getDisplay());
    }

    private function ingest(string $source, string $file, string $label): string
    {
        $tester = $this->console('app:knowledge:ingest', ['source' => $source, 'file' => self::FIXTURES . '/' . $file, '--label' => $label]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        return $this->uuidIn($tester->getDisplay());
    }

    /** @param array<string, mixed> $input */
    private function console(string $command, array $input = []): CommandTester
    {
        self::assertNotNull(self::$kernel);
        $tester = new CommandTester((new Application(self::$kernel))->find($command));
        $tester->execute($input);

        return $tester;
    }

    /** Sortie sur une ligne : les retours à la ligne de l'affichage ne coupent plus les phrases cherchées. */
    private function flat(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }

    private function uuidIn(string $display): string
    {
        self::assertSame(1, preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', $display, $match), $display);

        return $match[0];
    }

    /** @return list<array<string, mixed>> */
    private function passages(string $version): array
    {
        return $this->connection()->fetchAllAssociative('SELECT ordinal, page, section, article, text, fingerprint FROM knowledge_passage WHERE version_uuid = ? ORDER BY ordinal', [$version]);
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
