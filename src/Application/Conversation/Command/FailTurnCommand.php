<?php

declare(strict_types=1);

namespace App\Application\Conversation\Command;

use App\Application\CommandInterface;
use App\Domain\Conversation\TurnFailure;
use App\Domain\Conversation\TurnTrace;

/** Le tour n'a pas obtenu de réponse : il passe à « échoué », l'utilisateur peut relancer. */
final readonly class FailTurnCommand implements CommandInterface
{
    public function __construct(
        public string $turnUuid,
        public TurnFailure $failure,
        public TurnTrace $trace,
    ) {
    }
}
