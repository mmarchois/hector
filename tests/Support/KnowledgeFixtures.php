<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\CommandBusInterface;
use App\Application\Knowledge\Command\CreateSourceCommand;
use App\Application\Knowledge\Command\IngestDocumentCommand;
use App\Application\Knowledge\Command\PublishDocumentVersionCommand;
use App\Application\Knowledge\Command\WithdrawDocumentVersionCommand;
use App\Domain\Knowledge\DocumentVersion;
use App\Domain\Knowledge\Passage;
use App\Domain\Knowledge\Repository\PassageRepositoryInterface;
use App\Domain\Knowledge\Source;

/** Corpus de test : sources, versions et passages créés par les commandes de l'application. */
trait KnowledgeFixtures
{
    private const string KNOWLEDGE_FILES = __DIR__ . '/../Integration/Infrastructure/Console/knowledge';

    /**
     * Source, version ingérée depuis $file et, sauf mention contraire, publiée.
     *
     * @param array{corpus?: string, title?: string, excerptAllowed?: bool, excerptMax?: int|null, thirdParty?: bool, publish?: bool, kind?: string} $options
     */
    private function document(string $file, array $options = []): DocumentVersion
    {
        $bus = static::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        /** @var Source $source */
        $source = $bus->handle(new CreateSourceCommand(
            $options['corpus'] ?? 'regulatory',
            $options['title'] ?? 'Fiche d\'exemple',
            'Hector',
            $options['kind'] ?? 'hector_sheet',
            true,
            $options['excerptAllowed'] ?? true,
            $options['excerptMax'] ?? null,
            $options['thirdParty'] ?? true,
            'Contenu rédigé pour Hector',
        ));
        /** @var DocumentVersion $version */
        $version = $bus->handle(new IngestDocumentCommand($source->getUuid(), self::KNOWLEDGE_FILES . '/' . $file, 'v1', '2026-11-01', '2026-12-01'));
        if ($options['publish'] ?? true) {
            $bus->handle(new PublishDocumentVersionCommand($version->getUuid(), 'A. Expert'));
        }

        return $version;
    }

    private function withdraw(DocumentVersion $version): void
    {
        $bus = static::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);
        $bus->handle(new WithdrawDocumentVersionCommand($version->getUuid()));
    }

    /** @return list<Passage> */
    private function passagesOf(DocumentVersion $version): array
    {
        $repository = static::getContainer()->get(PassageRepositoryInterface::class);
        \assert($repository instanceof PassageRepositoryInterface);

        return $repository->findByVersion($version);
    }
}
