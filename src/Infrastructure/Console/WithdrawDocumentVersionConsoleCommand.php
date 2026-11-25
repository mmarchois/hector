<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\CommandBusInterface;
use App\Application\Knowledge\Command\WithdrawDocumentVersionCommand;
use App\Domain\Knowledge\DocumentVersion;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:knowledge:withdraw', description: 'Retire une version publiée de la recherche (ses passages restent consultables)')]
final readonly class WithdrawDocumentVersionConsoleCommand
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Identifiant de la version')]
        string $version,
    ): int {
        try {
            /** @var DocumentVersion $withdrawn */
            $withdrawn = $this->commandBus->handle(new WithdrawDocumentVersionCommand($version));
        } catch (\Throwable $e) {
            return ConsoleFailure::report($io, $e);
        }

        $io->success(\sprintf('Version « %s » retirée.', $withdrawn->getLabel()));

        return Command::SUCCESS;
    }
}
