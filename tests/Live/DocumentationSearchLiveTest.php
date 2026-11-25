<?php

declare(strict_types=1);

namespace App\Tests\Live;

use App\Application\Conversation\Port\ChatMessage;
use App\Application\Conversation\Port\ChatRole;
use App\Application\Conversation\Port\Participant;
use App\Application\Conversation\Port\ParticipantInterface;
use App\Application\Conversation\Service\AnswerEngine;
use App\Application\Conversation\Service\TurnContextBuilder;
use App\Application\Knowledge\Capability\DocumentationCapability;
use App\Application\Knowledge\Capability\SearchDocumentationTool;
use App\Application\Knowledge\Port\PassageHit;
use App\Application\Knowledge\Port\PassageSearchInterface;
use App\Domain\Conversation\Block;
use App\Domain\Conversation\Citation;
use App\Domain\Conversation\Repository\MessageRepositoryInterface;
use App\Domain\Conversation\StatementKind;
use App\Domain\Knowledge\Corpus;
use App\Domain\Knowledge\DocumentVersion;
use App\Domain\Knowledge\License;
use App\Domain\Knowledge\Passage;
use App\Domain\Knowledge\Repository\DocumentVersionRepositoryInterface;
use App\Domain\Knowledge\Source;
use App\Domain\Knowledge\SourceKind;
use App\Infrastructure\Adapter\FilePromptCatalog;
use App\Infrastructure\Adapter\Knowledge\HeadingChunker;
use App\Infrastructure\Adapter\Knowledge\MarkdownExtractor;
use App\Infrastructure\Adapter\Llm\Anthropic\AnthropicChatModel;
use App\Tests\Support\FrenchAnswerTexts;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Test vivant du lot L10 : une vraie question passe dans tout le circuit d'une réponse
 * sourcée, avec le vrai modèle. Consignes réelles, outil de recherche réel, moteur réel
 * (boucle d'outils, contrôle des citations) ; seule la recherche est remplacée par une
 * fiche d'exemple lue en mémoire, pour ne pas dépendre de la base de données.
 *
 * Payant (quelques centimes). Lancé à la main : make test_live ARGS="--filter DocumentationSearch".
 */
final class DocumentationSearchLiveTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../..';

    public function testHectorSearchesTheDocumentationThenCitesWhatItRead(): void
    {
        $localEnv = self::ROOT . '/.env.local';
        $env = is_file($localEnv) ? (new Dotenv())->parse((string) file_get_contents($localEnv)) : [];
        $apiKey = $env['ANTHROPIC_API_KEY'] ?? '';
        if ($apiKey === '') {
            self::markTestSkipped('ANTHROPIC_API_KEY absente de .env.local.');
        }

        // Une fiche de test, extraite et découpée par la vraie chaîne d'ingestion. Elle ne se dit
        // pas fictive : lors du premier essai, le modèle a lu une telle mention dans la fiche
        // d'exemple des autres tests et a refusé, à raison, de s'appuyer dessus
        $passages = $this->passages(__DIR__ . '/fiche-bouton-de-test.md');
        $search = new class($passages) implements PassageSearchInterface {
            /** @param list<Passage> $passages */
            public function __construct(private readonly array $passages)
            {
            }

            public function search(string $query, array $corpora, int $limit = 6, bool $transferableOnly = false): array
            {
                return array_map(static fn (Passage $passage): PassageHit => new PassageHit($passage, 1.0, false), \array_slice($this->passages, 0, $limit));
            }
        };

        $prompts = new FilePromptCatalog(self::ROOT . '/resources/prompts');
        $versions = $this->createStub(DocumentVersionRepositoryInterface::class);
        $versions->method('countPublished')->willReturn(1);
        $builder = new TurnContextBuilder(
            $prompts,
            $this->createStub(ParticipantInterface::class),
            $this->createStub(MessageRepositoryInterface::class),
            [new DocumentationCapability(new SearchDocumentationTool($search), $versions, $prompts)],
        );
        $prepared = $builder->compose(
            new Participant(guest: false, professional: false),
            [new ChatMessage(ChatRole::User, 'À quoi sert le bouton de test de mon différentiel ?')],
        );

        $model = new AnthropicChatModel($env['CHAT_MODEL'] ?? 'claude-opus-5-5', $env['CHAT_EFFORT'] ?? 'medium', 16000, 90.0, apiKey: $apiKey);
        $outcome = (new AnswerEngine($model, new FrenchAnswerTexts()))->run($prepared);
        $trace = $outcome->trace;

        fwrite(\STDERR, \sprintf(
            "\n[%s] issue : %s ; %d jetons en entrée, %d en sortie, %d lus du cache, %d écrits ; %d ms\nOutils : %s\nRejets : %s\nErreur : %s\n\n%s\n\nSources citées : %s\n",
            $trace->model ?? '?',
            $outcome->failed() ? 'échec' : ($outcome->degraded ? 'dégradée' : 'réponse'),
            $trace->inputTokens ?? 0,
            $trace->outputTokens ?? 0,
            $trace->cacheReadInputTokens ?? 0,
            $trace->cacheCreationInputTokens ?? 0,
            $trace->durationMs ?? 0,
            json_encode($trace->tools, \JSON_UNESCAPED_UNICODE),
            json_encode($trace->rejections, \JSON_UNESCAPED_UNICODE),
            $trace->error ?? 'aucune',
            implode("\n", array_map(static fn (Block $block): string => \sprintf('%s %s', $block->fallbackText, $block->citations() === [] ? '' : '→ ' . implode(', ', $block->citations())), $outcome->blocks)),
            implode(' | ', array_map(static fn (Citation $citation): string => \sprintf('%s (%s, %s)', $citation->id, $citation->title, $citation->section ?? '—'), $outcome->citations)),
        ));

        // Le fournisseur a répondu, et la boucle d'outils est allée à son terme
        self::assertFalse($outcome->failed(), 'Le fournisseur n\'a pas répondu : ' . ($trace->error ?? ''));
        self::assertSame(SearchDocumentationTool::NAME, $trace->tools[0]['name'] ?? null, 'Le modèle n\'a pas appelé la recherche.');
        self::assertFalse($outcome->degraded, 'Les deux brouillons ont été rejetés par le contrôle.');

        // Au moins un fait sourcé, appuyé sur un passage que la recherche a rendu
        $ids = array_map(static fn (Passage $passage): string => $passage->getUuid(), $passages);
        self::assertNotSame([], $outcome->citations, 'La réponse ne cite aucune source.');
        foreach ($outcome->citations as $citation) {
            self::assertContains($citation->id, $ids);
        }
        self::assertNotSame([], array_filter($outcome->blocks, static fn (Block $block): bool => $block->kind() === StatementKind::SourcedFact && $block->citations() !== []));
    }

    /** @return list<Passage> */
    private function passages(string $file): array
    {
        $now = new \DateTimeImmutable('2026-11-25 10:00:00');
        $source = new Source('source-1', Corpus::Regulatory, 'Fiche Hector : le bouton de test d\'un dispositif différentiel', 'Hector', SourceKind::HectorSheet, new License(true, true, null, true, 'Contenu rédigé pour Hector'), [], $now);
        $version = new DocumentVersion('version-1', $source, 'v1', new \DateTimeImmutable('2026-11-01'), null, null, null, basename($file), hash_file('sha256', $file) ?: '', 'cle', $now);

        $passages = [];
        foreach ((new HeadingChunker())->chunk((new MarkdownExtractor())->extract($file)) as $index => $draft) {
            $passages[] = new Passage('passage-' . ($index + 1), $version, $index + 1, $draft->page, $draft->section, $draft->article, $draft->text, Passage::fingerprintOf($draft->page, $draft->section, $draft->article, $draft->text));
        }

        return $passages;
    }
}
