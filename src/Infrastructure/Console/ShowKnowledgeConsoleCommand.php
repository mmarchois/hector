<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\Knowledge\Query\DocumentVersionDetail;
use App\Application\Knowledge\Query\GetDocumentVersionQuery;
use App\Application\Knowledge\Query\ListSourcesQuery;
use App\Application\Knowledge\Query\SourceOverview;
use App\Application\QueryBusInterface;
use App\Domain\Knowledge\DocumentVersion;
use App\Domain\Knowledge\Passage;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Sans argument : les sources et leurs versions. Avec une version : ses passages, à relire avant publication. */
#[AsCommand(name: 'app:knowledge:show', description: 'Affiche la base documentaire, ou les passages d\'une version à relire')]
final readonly class ShowKnowledgeConsoleCommand
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Identifiant d\'une version, pour en lire les passages')]
        ?string $version = null,
    ): int {
        if ($version === null) {
            return $this->overview($io);
        }

        try {
            /** @var DocumentVersionDetail $detail */
            $detail = $this->queryBus->handle(new GetDocumentVersionQuery($version));
        } catch (\Throwable $e) {
            return ConsoleFailure::report($io, $e);
        }

        $document = $detail->version;
        $source = $document->getSource();
        $license = $source->getLicense();
        $io->title(\sprintf('%s — %s', $source->getTitle(), $document->getLabel()));
        $io->definitionList(
            ['Corpus' => $source->getCorpus()->label()],
            ['Genre' => $source->getKind()->label()],
            ['Éditeur' => $source->getPublisher()],
            ['Licence' => \sprintf(
                '%s ; extrait %s ; transmission à un sous-traitant %s',
                $license->origin,
                $license->excerptAllowed ? 'affichable' . ($license->excerptMaxCharacters !== null ? \sprintf(' (%d caractères au plus)', $license->excerptMaxCharacters) : '') : 'non affichable',
                $license->thirdPartyTransferAllowed ? 'permise' : 'interdite',
            )],
            ['Statut' => $this->status($document)],
            ['Publié par l\'éditeur le' => $document->getPublishedOn()?->format('Y-m-d') ?? 'non renseigné'],
            ['Fichier' => \sprintf('%s (empreinte %s)', $document->getFileName(), substr($document->getFileFingerprint(), 0, 12))],
            ['Chaîne de traitement' => $detail->lastRun === null ? '—' : \sprintf('v%s, %s, %s', $detail->lastRun->getPipelineVersion(), $detail->lastRun->getExtractor(), $detail->lastRun->getChunker())],
        );

        $io->table(
            ['N°', 'Page', 'Section', 'Article', 'Texte'],
            array_map(static fn (Passage $passage): array => [
                $passage->getPosition(),
                $passage->getPage() ?? '—',
                $passage->getSection() ?? '—',
                $passage->getArticle() ?? '—',
                $passage->getText(),
            ], $detail->passages),
        );

        return Command::SUCCESS;
    }

    private function overview(SymfonyStyle $io): int
    {
        /** @var list<SourceOverview> $sources */
        $sources = $this->queryBus->handle(new ListSourcesQuery());
        if ($sources === []) {
            $io->writeln('Aucune source. Commencez par app:knowledge:source:create.');

            return Command::SUCCESS;
        }

        foreach ($sources as $overview) {
            $source = $overview->source;
            $io->section(\sprintf('%s — %s (%s, %s)', $source->getTitle(), $source->getPublisher(), $source->getCorpus()->label(), $source->getKind()->label()));
            $io->writeln(\sprintf('Source %s%s', $source->getUuid(), $source->getLicense()->indexingAllowed ? '' : ' — indexation interdite par la licence'));
            $io->table(
                ['Version', 'Statut', 'Identifiant'],
                array_map(fn (DocumentVersion $document): array => [$document->getLabel(), $this->status($document), $document->getUuid()], $overview->versions),
            );
        }

        return Command::SUCCESS;
    }

    private function status(DocumentVersion $document): string
    {
        return match (true) {
            $document->getWithdrawnAt() !== null => \sprintf('retirée le %s', $document->getWithdrawnAt()->format('Y-m-d')),
            $document->getReviewedAt() !== null => \sprintf('publiée, relue par %s le %s', $document->getReviewedBy(), $document->getReviewedAt()->format('Y-m-d')),
            default => 'brouillon, à relire',
        };
    }
}
