<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\CommandBusInterface;
use App\Application\Knowledge\Command\PublishDocumentVersionCommand;
use App\Domain\Knowledge\DocumentVersion;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:knowledge:publish', description: 'Publie une version relue : elle ne changera plus')]
final readonly class PublishDocumentVersionConsoleCommand
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Identifiant de la version')]
        string $version,
        #[Option(description: 'Nom de la personne qui a relu les passages')]
        string $reviewer = '',
    ): int {
        try {
            /** @var DocumentVersion $published */
            $published = $this->commandBus->handle(new PublishDocumentVersionCommand($version, $reviewer));
        } catch (\Throwable $e) {
            return ConsoleFailure::report($io, $e);
        }

        $io->success(\sprintf('Version « %s » publiée, relue par %s.', $published->getLabel(), $published->getReviewedBy()));

        return Command::SUCCESS;
    }
}
