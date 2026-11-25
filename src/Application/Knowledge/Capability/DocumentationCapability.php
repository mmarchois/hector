<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Capability;

use App\Application\Conversation\Capability\CapabilityInterface;
use App\Application\Conversation\Port\Participant;
use App\Application\Conversation\Port\PromptCatalogInterface;
use App\Domain\Knowledge\Repository\DocumentVersionRepositoryInterface;

/**
 * Capacité « documentation » : donne au modèle l'outil de recherche dans la base
 * documentaire et les consignes de citation. Sans aucun document publié, l'outil n'est pas
 * offert et les consignes disent au modèle qu'il n'a pas de base : il ne cite alors rien.
 */
final readonly class DocumentationCapability implements CapabilityInterface
{
    public function __construct(
        private SearchDocumentationTool $searchTool,
        private DocumentVersionRepositoryInterface $versionRepository,
        private PromptCatalogInterface $prompts,
    ) {
    }

    public function tools(Participant $participant): array
    {
        return $this->available() ? [$this->searchTool] : [];
    }

    public function instructions(Participant $participant): string
    {
        return $this->prompts->get($this->available() ? 'documentation-available' : 'documentation-unavailable')->text;
    }

    private function available(): bool
    {
        return $this->versionRepository->countPublished() > 0;
    }
}
