<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\User\Port\TermsOfUseInterface;
use App\Domain\Port\DateUtilsInterface;
use App\Domain\Port\IdFactoryInterface;
use App\Domain\User\Exception\TermsOfUseNotAcceptedException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;

final readonly class CreateGuestCommandHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TermsOfUseInterface $termsOfUse,
        private IdFactoryInterface $idFactory,
        private DateUtilsInterface $dateUtils,
    ) {
    }

    public function __invoke(CreateGuestCommand $command): User
    {
        if ($command->acceptedTermsOfUseVersion !== $this->termsOfUse->currentVersion()) {
            throw new TermsOfUseNotAcceptedException();
        }

        $now = $this->dateUtils->getNow();

        // ID1 et ID3 : ni nom, ni email, ni mot de passe, et la qualification la plus restrictive
        $guest = (new User($this->idFactory->make()))
            ->setRoles([User::ROLE_USER])
            ->setProfessional(false)
            ->setRegistrationDate($now)
            ->acceptTermsOfUse($command->acceptedTermsOfUseVersion, $now);

        $this->userRepository->add($guest);

        return $guest;
    }
}
