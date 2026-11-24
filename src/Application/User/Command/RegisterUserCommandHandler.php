<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Company\Service\CompanyProvider;
use App\Application\StringUtilsInterface;
use App\Application\User\Port\TermsOfUseInterface;
use App\Domain\Port\DateUtilsInterface;
use App\Domain\Port\IdFactoryInterface;
use App\Domain\Port\PasswordHasherInterface;
use App\Domain\User\Exception\EmailAlreadyExistsException;
use App\Domain\User\Exception\TermsOfUseNotAcceptedException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Specification\IsEmailAlreadyExists;
use App\Domain\User\User;

final readonly class RegisterUserCommandHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private IsEmailAlreadyExists $isEmailAlreadyExists,
        private CompanyProvider $companyProvider,
        private TermsOfUseInterface $termsOfUse,
        private IdFactoryInterface $idFactory,
        private DateUtilsInterface $dateUtils,
        private StringUtilsInterface $stringUtils,
        private PasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(RegisterUserCommand $command): User
    {
        $email = $this->stringUtils->normalizeEmail($command->email);

        if ($this->isEmailAlreadyExists->isSatisfiedBy($email)) {
            throw new EmailAlreadyExistsException();
        }

        // ID2 : un invité devient un compte complet sans changer d'uuid, il garde donc ses
        // données et sa session. Sinon, un nouveau compte est créé.
        $user = $this->guestToConvert($command) ?? $this->newUser($command);

        $user
            ->setFullName(trim($command->fullName))
            ->setEmail($email)
            ->setProfessional($command->professional);

        // Entreprise, SIRET et TVA sont garantis non vides pour un pro par la validation de la commande
        if ($command->professional) {
            $user->setCompany($this->companyProvider->provide(
                trim((string) $command->companyName),
                (string) $command->siret,
                (string) $command->vatNumber,
            ));
        }

        $user->setPassword($this->passwordHasher->hash($user, $command->password));

        return $user;
    }

    private function guestToConvert(RegisterUserCommand $command): ?User
    {
        if ($command->currentUserUuid === null) {
            return null;
        }

        $user = $this->userRepository->findOneByUuid($command->currentUserUuid);

        return $user instanceof User && $user->isGuest() ? $user : null;
    }

    private function newUser(RegisterUserCommand $command): User
    {
        if ($command->acceptedTermsOfUseVersion !== $this->termsOfUse->currentVersion()) {
            throw new TermsOfUseNotAcceptedException();
        }

        $now = $this->dateUtils->getNow();
        $user = (new User($this->idFactory->make()))
            ->setRoles([User::ROLE_USER])
            ->setRegistrationDate($now)
            ->acceptTermsOfUse($command->acceptedTermsOfUseVersion, $now);

        $this->userRepository->add($user);

        return $user;
    }
}
