<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\CommandBusInterface;
use App\Application\Knowledge\Command\IngestDocumentCommand;
use App\Domain\Knowledge\DocumentVersion;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** L'ingestion se lance à la main (décision D9) ; la version produite est un brouillon à relire. */
#[AsCommand(name: 'app:knowledge:ingest', description: 'Ingère un fichier comme version d\'une source (brouillon à relire avant publication)')]
final readonly class IngestDocumentConsoleCommand
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Identifiant de la source')]
        string $source,
        #[Argument(description: 'Fichier à ingérer (.md, .markdown, .txt)')]
        string $file,
        #[Option(description: 'Libellé de la version : « édition 2024 », « v2 »…')]
        string $label = '',
        #[Option(name: 'published-on', description: 'Date de publication par l\'éditeur (AAAA-MM-JJ)')]
        ?string $publishedOn = null,
        #[Option(name: 'effective-from', description: 'Date d\'effet (AAAA-MM-JJ)')]
        ?string $effectiveFrom = null,
        #[Option(name: 'effective-until', description: 'Date de fin d\'effet (AAAA-MM-JJ)')]
        ?string $effectiveUntil = null,
        #[Option(description: 'Identifiant de la version remplacée')]
        ?string $replaces = null,
    ): int {
        try {
            /** @var DocumentVersion $version */
            $version = $this->commandBus->handle(new IngestDocumentCommand($source, $file, $label, $publishedOn, $effectiveFrom, $effectiveUntil, $replaces));
        } catch (\Throwable $e) {
            return ConsoleFailure::report($io, $e);
        }

        $io->success(\sprintf('Version « %s » ingérée en brouillon : %s', $version->getLabel(), $version->getUuid()));
        $io->writeln(\sprintf('À relire : bin/console app:knowledge:show %s', $version->getUuid()));

        return Command::SUCCESS;
    }
}
