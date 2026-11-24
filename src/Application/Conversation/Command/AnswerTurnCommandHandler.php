<?php

declare(strict_types=1);

namespace App\Application\Conversation\Command;

use App\Domain\Conversation\Exception\TurnNotFoundException;
use App\Domain\Conversation\Message;
use App\Domain\Conversation\MessageRole;
use App\Domain\Conversation\Repository\MessageRepositoryInterface;
use App\Domain\Conversation\Repository\TurnRepositoryInterface;
use App\Domain\Conversation\Turn;
use App\Domain\Port\DateUtilsInterface;
use App\Domain\Port\IdFactoryInterface;

final readonly class AnswerTurnCommandHandler
{
    public function __construct(
        private TurnRepositoryInterface $turnRepository,
        private MessageRepositoryInterface $messageRepository,
        private IdFactoryInterface $idFactory,
        private DateUtilsInterface $dateUtils,
    ) {
    }

    public function __invoke(AnswerTurnCommand $command): Turn
    {
        $turn = $this->turnRepository->findOneByUuid($command->turnUuid);
        if (!$turn instanceof Turn) {
            throw new TurnNotFoundException();
        }

        $now = $this->dateUtils->getNow();
        $conversation = $turn->getConversation();
        $answer = $this->messageRepository->add(
            new Message($this->idFactory->make(), $conversation, MessageRole::Assistant, $command->blocks, $now),
        );
        $conversation->recordActivity($now);

        return $turn->answer($answer, $now, $command->degraded);
    }
}
