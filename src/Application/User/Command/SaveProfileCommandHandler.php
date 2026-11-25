<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Company\Service\CompanyProvider;
use App\Application\StringUtilsInterface;
use App\Domain\Port\PasswordHasherInterface;
use App\Domain\User\Exception\EmailAlreadyExistsException;
use App\Domain\User\Exception\GuestAccountException;
use App\Domain\User\Exception\InvalidCurrentPasswordException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\RefreshTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Specification\IsEmailAlreadyExists;
use App\Domain\User\User;

final readonly class SaveProfileCommandHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private IsEmailAlreadyExists $isEmailAlreadyExists,
        private CompanyProvider $companyProvider,
        private StringUtilsInterface $stringUtils,
        private PasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(SaveProfileCommand $command): User
    {
        $user = $this->userRepository->findOneByUuid($command->userUuid);
        if (!$user instanceof User) {
            throw new UserNotFoundException();
        }

        // Un invité n'a pas de profil à modifier : il crée un compte (ID1, ID2)
        if ($user->isGuest()) {
            throw new GuestAccountException();
        }

        $email = $this->stringUtils->normalizeEmail($command->email);
        if ($email !== $user->getEmail() && $this->isEmailAlreadyExists->isSatisfiedBy($email)) {
            throw new EmailAlreadyExistsException();
        }

        // Vérifié avant toute modification : un mot de passe actuel faux n'enregistre rien
        $newPassword = (string) $command->newPassword;
        if ($newPassword !== '' && !$this->passwordHasher->isValid($user, (string) $command->currentPassword)) {
            throw new InvalidCurrentPasswordException();
        }

        $user
            ->setEmail($email)
            ->setFullName(trim($command->fullName))
            ->setProfessional($command->professional);

        $this->updateCompany($user, $command);

        if ($newPassword !== '') {
            $user->setPassword($this->passwordHasher->hash($user, $newPassword));

            // Un refresh token volé ne doit pas survivre à un changement de mot de passe
            $this->refreshTokenRepository->removeAllByUser($user->getUuid());
        }

        return $user;
    }

    private function updateCompany(User $user, SaveProfileCommand $command): void
    {
        if (!$command->professional) {
            $user->setCompany(null);

            return;
        }

        $name = trim((string) $command->companyName);
        $vatNumber = (string) $command->vatNumber;
        $current = $user->getCompany();
        $company = $this->companyProvider->provide($name, (string) $command->siret, $vatNumber);

        // Seule l'entreprise déjà rattachée au compte est modifiable : rejoindre une entreprise
        // existante (autre SIRET) ne doit pas réécrire ses informations.
        if ($current !== null && $company->getUuid() === $current->getUuid()) {
            $company->setName($name)->setVatNumber($vatNumber);
        }

        $user->setCompany($company);
    }
}
