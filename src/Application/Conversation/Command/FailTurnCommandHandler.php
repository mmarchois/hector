<?php

declare(strict_types=1);

namespace App\Application\Conversation\Command;

use App\Domain\Conversation\Exception\TurnNotFoundException;
use App\Domain\Conversation\Repository\TurnRepositoryInterface;
use App\Domain\Conversation\Turn;
use App\Domain\Port\DateUtilsInterface;

final readonly class FailTurnCommandHandler
{
    public function __construct(
        private TurnRepositoryInterface $turnRepository,
        private DateUtilsInterface $dateUtils,
    ) {
    }

    public function __invoke(FailTurnCommand $command): Turn
    {
        $turn = $this->turnRepository->findOneByUuid($command->turnUuid);
        if (!$turn instanceof Turn) {
            throw new TurnNotFoundException();
        }

        return $turn->fail($this->dateUtils->getNow(), $command->failure, $command->trace);
    }
}
