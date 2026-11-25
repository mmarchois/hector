<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\CommandBusInterface;
use App\Application\User\Command\PurgeGuestsCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/** À planifier une fois par jour (cron) : supprime les invités sans session depuis 30 jours (ID4). */
#[AsCommand(name: 'app:guests:purge', description: 'Supprime les comptes invités qui n\'ont plus de session')]
final readonly class PurgeGuestsConsoleCommand
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        /** @var int $count */
        $count = $this->commandBus->handle(new PurgeGuestsCommand());
        $output->writeln(\sprintf('%d compte(s) invité(s) supprimé(s).', $count));

        return Command::SUCCESS;
    }
}
