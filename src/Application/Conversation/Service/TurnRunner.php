<?php

declare(strict_types=1);

namespace App\Application\Conversation\Service;

use App\Application\CommandBusInterface;
use App\Application\Conversation\Command\AnswerTurnCommand;
use App\Application\Conversation\Command\FailTurnCommand;
use App\Application\Conversation\Command\SendMessageCommand;
use App\Domain\Conversation\Turn;

/**
 * Déroulé d'un tour (docs/ARCHITECTURE.md, § 5) : deux transactions courtes et, entre les
 * deux, hors de toute transaction, l'appel au modèle et le contrôle de son brouillon
 * (AnswerEngine).
 */
final readonly class TurnRunner
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private TurnContextBuilder $contextBuilder,
        private AnswerEngine $engine,
    ) {
    }

    public function send(string $conversationUuid, string $ownerUuid, string $text, string $locale = 'fr'): Turn
    {
        // 1. Message de l'utilisateur et tour « en cours » (transaction courte)
        /** @var Turn $turn */
        $turn = $this->commandBus->handle(new SendMessageCommand($conversationUuid, $ownerUuid, $text));

        // 2. Contexte, appels au modèle et contrôle, hors transaction
        $outcome = $this->engine->run($this->contextBuilder->build($turn, $locale));

        // 3. Réponse ou échec, et trace (transaction courte)
        /** @var Turn $finished */
        $finished = $this->commandBus->handle($outcome->failure !== null
            ? new FailTurnCommand($turn->getUuid(), $outcome->failure, $outcome->trace)
            : new AnswerTurnCommand($turn->getUuid(), $outcome->blocks, $outcome->degraded, $outcome->trace));

        return $finished;
    }
}
