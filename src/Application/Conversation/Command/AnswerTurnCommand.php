<?php

declare(strict_types=1);

namespace App\Application\Conversation\Command;

use App\Application\CommandInterface;
use App\Domain\Conversation\Block;
use App\Domain\Conversation\TurnTrace;

/** Enregistre la réponse de l'assistant et la trace du tour, et termine le tour. */
final readonly class AnswerTurnCommand implements CommandInterface
{
    /**
     * @param list<Block> $blocks
     */
    public function __construct(
        public string $turnUuid,
        public array $blocks,
        public bool $degraded = false,
        public ?TurnTrace $trace = null,
    ) {
    }
}
