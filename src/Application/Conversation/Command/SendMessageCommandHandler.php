<?php

declare(strict_types=1);

namespace App\Application\Conversation\Command;

use App\Application\Conversation\Service\FreeAllowancePolicy;
use App\Domain\Conversation\Block;
use App\Domain\Conversation\Conversation;
use App\Domain\Conversation\Exception\ConversationNotFoundException;
use App\Domain\Conversation\Message;
use App\Domain\Conversation\MessageRole;
use App\Domain\Conversation\Repository\ConversationRepositoryInterface;
use App\Domain\Conversation\Repository\MessageRepositoryInterface;
use App\Domain\Conversation\Repository\TurnRepositoryInterface;
use App\Domain\Conversation\Turn;
use App\Domain\Port\DateUtilsInterface;
use App\Domain\Port\IdFactoryInterface;

final readonly class SendMessageCommandHandler
{
    public function __construct(
        private ConversationRepositoryInterface $conversationRepository,
        private MessageRepositoryInterface $messageRepository,
        private TurnRepositoryInterface $turnRepository,
        private FreeAllowancePolicy $freeAllowance,
        private IdFactoryInterface $idFactory,
        private DateUtilsInterface $dateUtils,
    ) {
    }

    public function __invoke(SendMessageCommand $command): Turn
    {
        $conversation = $this->conversationRepository->findOneByUuid($command->conversationUuid);
        if (!$conversation instanceof Conversation || !$conversation->isOwnedBy($command->ownerUuid)) {
            throw new ConversationNotFoundException();
        }

        $now = $this->dateUtils->getNow();
        // CV8 : refusé avant d'enregistrer quoi que ce soit, et donc avant tout appel au modèle
        $this->freeAllowance->assertAllowed($command->ownerUuid, $now);

        $text = trim($command->text);
        $message = $this->messageRepository->add(
            new Message($this->idFactory->make(), $conversation, MessageRole::User, [Block::text($text)], $now),
        );
        $conversation->recordUserMessage($text, $now);

        return $this->turnRepository->add(new Turn($this->idFactory->make(), $conversation, $message, $now));
    }
}
