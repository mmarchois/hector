<?php

declare(strict_types=1);

namespace App\Application\Calculation\Capability;

use App\Application\Calculation\Service\CalculationPresenter;
use App\Application\CommandBusInterface;
use App\Application\Conversation\Capability\CapabilityInterface;
use App\Application\Conversation\Port\Participant;
use App\Application\Conversation\Port\PromptCatalogInterface;
use App\Domain\Calculation\CalculatorCatalog;

/**
 * Capacité « calcul » : donne au modèle l'outil de calcul et ses consignes. L'outil est
 * construit pour l'interlocuteur du tour : il enregistre en son nom et compose dans sa langue.
 */
final readonly class CalculationCapability implements CapabilityInterface
{
    public function __construct(
        private CalculatorCatalog $catalog,
        private CommandBusInterface $commandBus,
        private CalculationBlocks $blocks,
        private CalculationPresenter $presenter,
        private PromptCatalogInterface $prompts,
    ) {
    }

    public function tools(Participant $participant): array
    {
        return [new CalculateTool($this->catalog, $this->commandBus, $this->blocks, $this->presenter, $participant->uuid, $participant->locale)];
    }

    public function instructions(Participant $participant): string
    {
        return $this->prompts->get('calculation')->text;
    }
}
