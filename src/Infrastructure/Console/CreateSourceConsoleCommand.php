<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\CommandBusInterface;
use App\Application\Knowledge\Command\CreateSourceCommand;
use App\Domain\Knowledge\Source;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:knowledge:source:create', description: 'Déclare une source documentaire, avec son corpus et sa licence')]
final readonly class CreateSourceConsoleCommand
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    /**
     * @param list<string> $domain
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Titre de la source')]
        string $title,
        #[Option(description: 'Éditeur de la source')]
        string $publisher = '',
        #[Option(name: 'license-origin', description: 'D\'où vient le droit de l\'utiliser : licence ouverte, accord écrit, contenu rédigé pour Hector…')]
        string $licenseOrigin = '',
        #[Option(description: 'Genre : standard, regulation, guide, manufacturer_manual, datasheet, hector_sheet')]
        string $kind = 'hector_sheet',
        #[Option(description: 'Corpus : regulatory ou products')]
        string $corpus = 'regulatory',
        #[Option(name: 'no-indexing', description: 'La licence interdit l\'indexation : la source ne pourra pas être ingérée')]
        bool $noIndexing = false,
        #[Option(name: 'no-excerpt', description: 'La licence interdit d\'afficher un extrait')]
        bool $noExcerpt = false,
        #[Option(name: 'excerpt-max', description: 'Longueur maximale d\'un extrait affiché, en caractères')]
        ?int $excerptMax = null,
        #[Option(name: 'no-third-party', description: 'La licence interdit la transmission à un sous-traitant (fournisseur du modèle)')]
        bool $noThirdParty = false,
        #[Option(description: 'Domaine couvert (répétable)')]
        array $domain = [],
    ): int {
        try {
            /** @var Source $source */
            $source = $this->commandBus->handle(new CreateSourceCommand(
                $corpus,
                $title,
                $publisher,
                $kind,
                !$noIndexing,
                !$noExcerpt,
                $excerptMax,
                !$noThirdParty,
                $licenseOrigin,
                $domain,
            ));
        } catch (\Throwable $e) {
            return ConsoleFailure::report($io, $e);
        }

        $io->success(\sprintf('Source créée : %s (%s)', $source->getTitle(), $source->getUuid()));

        return Command::SUCCESS;
    }
}
