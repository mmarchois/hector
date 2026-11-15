<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Company\Service\CompanyProvider;
use App\Application\StringUtilsInterface;
use App\Domain\Port\DateUtilsInterface;
use App\Domain\Port\IdFactoryInterface;
use App\Domain\Port\PasswordHasherInterface;
use App\Domain\User\Exception\EmailAlreadyExistsException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Specification\IsEmailAlreadyExists;
use App\Domain\User\User;

final readonly class RegisterUserCommandHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private IsEmailAlreadyExists $isEmailAlreadyExists,
        private CompanyProvider $companyProvider,
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

        $user = (new User($this->idFactory->make()))
            ->setFullName(trim($command->fullName))
            ->setEmail($email)
            ->setRoles([User::ROLE_USER])
            ->setProfessional($command->professional)
            ->setRegistrationDate($this->dateUtils->getNow());

        // Entreprise, SIRET et TVA sont garantis non vides pour un pro par la validation de la commande
        if ($command->professional) {
            $user->setCompany($this->companyProvider->provide(
                trim((string) $command->companyName),
                (string) $command->siret,
                (string) $command->vatNumber,
            ));
        }

        $user->setPassword($this->passwordHasher->hash($user, $command->password));

        $this->userRepository->add($user);

        return $user;
    }
}
