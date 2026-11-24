<?php

declare(strict_types=1);

namespace App\Application\Conversation\Command;

use App\Domain\Conversation\Conversation;
use App\Domain\Conversation\Repository\ConversationRepositoryInterface;
use App\Domain\Port\DateUtilsInterface;
use App\Domain\Port\IdFactoryInterface;

final readonly class CreateConversationCommandHandler
{
    public function __construct(
        private ConversationRepositoryInterface $conversationRepository,
        private IdFactoryInterface $idFactory,
        private DateUtilsInterface $dateUtils,
    ) {
    }

    public function __invoke(CreateConversationCommand $command): Conversation
    {
        return $this->conversationRepository->add(
            new Conversation($this->idFactory->make(), $command->ownerUuid, $this->dateUtils->getNow()),
        );
    }
}
