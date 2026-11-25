<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Application\Conversation\Port\Participant;
use App\Application\Conversation\Port\ParticipantInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;

/** Le type de compte de l'auteur d'un message, lu dans le contexte User (pont d'infrastructure). */
final readonly class UserParticipant implements ParticipantInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function profileOf(string $userUuid): Participant
    {
        $user = $this->userRepository->findOneByUuid($userUuid);

        // Inconnu : traité comme le plus restrictif, un invité particulier (SF2)
        return $user instanceof User
            ? new Participant(guest: $user->isGuest(), professional: $user->isProfessional())
            : new Participant(guest: true, professional: false);
    }
}
