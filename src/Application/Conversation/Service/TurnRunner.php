<?php

declare(strict_types=1);

namespace App\Application\Conversation\Service;

use App\Application\CommandBusInterface;
use App\Application\Conversation\Command\AnswerTurnCommand;
use App\Application\Conversation\Command\SendMessageCommand;
use App\Domain\Conversation\Block;
use App\Domain\Conversation\Turn;

/**
 * Déroulé d'un tour (docs/ARCHITECTURE.md, § 5) : deux transactions courtes, et entre
 * les deux, hors de toute transaction, la production de la réponse.
 *
 * Lot L4 : la réponse est un texte fixe, derrière le contrat définitif. Le lot L5 la
 * fait produire par le modèle de langage, à la même place.
 */
final readonly class TurnRunner
{
    public const string PLACEHOLDER_REPLY = 'Hector n\'est pas encore relié à son assistant IA. Votre message est bien enregistré ; les réponses arrivent dans une prochaine version.';

    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function send(string $conversationUuid, string $ownerUuid, string $text): Turn
    {
        /** @var Turn $turn */
        $turn = $this->commandBus->handle(new SendMessageCommand($conversationUuid, $ownerUuid, $text));

        $reply = [Block::text(self::PLACEHOLDER_REPLY)];

        /** @var Turn $answered */
        $answered = $this->commandBus->handle(new AnswerTurnCommand($turn->getUuid(), $reply));

        return $answered;
    }
}
