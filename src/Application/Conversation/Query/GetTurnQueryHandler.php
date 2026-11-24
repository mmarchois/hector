<?php

declare(strict_types=1);

namespace App\Application\Conversation\Query;

use App\Domain\Conversation\Exception\TurnNotFoundException;
use App\Domain\Conversation\Repository\TurnRepositoryInterface;
use App\Domain\Conversation\Turn;

final readonly class GetTurnQueryHandler
{
    public function __construct(
        private TurnRepositoryInterface $turnRepository,
    ) {
    }

    public function __invoke(GetTurnQuery $query): Turn
    {
        $turn = $this->turnRepository->findOneByUuid($query->turnUuid);
        if (!$turn instanceof Turn || !$turn->getConversation()->isOwnedBy($query->ownerUuid)) {
            throw new TurnNotFoundException();
        }

        return $turn;
    }
}
