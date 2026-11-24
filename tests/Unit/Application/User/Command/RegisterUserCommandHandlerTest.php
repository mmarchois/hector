<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Command;

use App\Application\Company\Service\CompanyProvider;
use App\Application\StringUtilsInterface;
use App\Application\User\Command\RegisterUserCommand;
use App\Application\User\Command\RegisterUserCommandHandler;
use App\Application\User\Port\TermsOfUseInterface;
use App\Domain\Company\Company;
use App\Domain\Company\Repository\CompanyRepositoryInterface;
use App\Domain\Port\DateUtilsInterface;
use App\Domain\Port\IdFactoryInterface;
use App\Domain\Port\PasswordHasherInterface;
use App\Domain\User\Exception\EmailAlreadyExistsException;
use App\Domain\User\Exception\TermsOfUseNotAcceptedException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Specification\IsEmailAlreadyExists;
use App\Domain\User\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RegisterUserCommandHandlerTest extends TestCase
{
    private const string TERMS = '2026-11-24';

    public function testRegistersAnIndividualWithoutCompany(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findOneByEmail')->willReturn(null);
        $userRepository->expects(self::once())->method('add')->willReturnArgument(0);

        $companyRepository = $this->createMock(CompanyRepositoryInterface::class);
        $companyRepository->expects(self::never())->method('add');

        $user = $this->handler($userRepository, $companyRepository)(
            new RegisterUserCommand(' Jean Dupont ', ' Jean@Exemple.fr', 'motdepasse123', acceptedTermsOfUseVersion: self::TERMS),
        );

        self::assertSame('user-uuid', $user->getUuid());
        self::assertFalse($user->isGuest());
        self::assertSame('Jean Dupont', $user->getFullName());
        self::assertSame('jean@exemple.fr', $user->getEmail());
        self::assertSame([User::ROLE_USER], $user->getRoles());
        self::assertSame('hashed:motdepasse123', $user->getPassword());
        self::assertEquals(new \DateTimeImmutable('2026-11-15 10:00:00'), $user->getRegistrationDate());
        self::assertSame(self::TERMS, $user->getTermsOfUseVersion());
        self::assertEquals(new \DateTimeImmutable('2026-11-15 10:00:00'), $user->getTermsOfUseAcceptedAt());
        self::assertFalse($user->isProfessional());
        self::assertNull($user->getCompany());
    }

    public function testRegistersAProfessionalAndCreatesTheCompany(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findOneByEmail')->willReturn(null);

        $companyRepository = $this->createMock(CompanyRepositoryInterface::class);
        $companyRepository->expects(self::once())->method('findOneBySiret')->with('12345678901234')->willReturn(null);
        $companyRepository->expects(self::once())->method('add')->willReturnArgument(0);

        $user = $this->handler($userRepository, $companyRepository)(new RegisterUserCommand(
            'Marie Ohm',
            'marie@exemple.fr',
            'motdepasse123',
            true,
            ' Ohm Élec ',
            '12345678901234',
            'FR12345678901',
            self::TERMS,
        ));

        self::assertTrue($user->isProfessional());
        $company = $user->getCompany();
        self::assertInstanceOf(Company::class, $company);
        self::assertSame('company-uuid', $company->getUuid());
        self::assertSame('Ohm Élec', $company->getName());
        self::assertSame('12345678901234', $company->getSiret());
        self::assertSame('FR12345678901', $company->getVatNumber());
    }

    public function testReusesTheExistingCompanyWithTheSameSiret(): void
    {
        $existing = (new Company('existing-company-uuid'))
            ->setName('Ohm Élec')
            ->setSiret('12345678901234')
            ->setVatNumber('FR12345678901');

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findOneByEmail')->willReturn(null);

        $companyRepository = $this->createMock(CompanyRepositoryInterface::class);
        $companyRepository->method('findOneBySiret')->willReturn($existing);
        $companyRepository->expects(self::never())->method('add');

        $user = $this->handler($userRepository, $companyRepository)(new RegisterUserCommand(
            'Paul Volt',
            'paul@exemple.fr',
            'motdepasse123',
            true,
            'Un autre nom',
            '12345678901234',
            'FR99999999999',
            self::TERMS,
        ));

        // Le collègue rejoint l'entreprise sans en réécrire les informations
        self::assertSame($existing, $user->getCompany());
        self::assertSame('Ohm Élec', $existing->getName());
        self::assertSame('FR12345678901', $existing->getVatNumber());
    }

    public function testRejectsAnAlreadyUsedEmail(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('findOneByEmail')->with('jean@exemple.fr')->willReturn(new User('existing-uuid'));
        $userRepository->expects(self::never())->method('add');

        $this->expectException(EmailAlreadyExistsException::class);

        $this->handler($userRepository, $this->createStub(CompanyRepositoryInterface::class))(
            new RegisterUserCommand('Jean Dupont', 'JEAN@exemple.fr', 'motdepasse123', acceptedTermsOfUseVersion: self::TERMS),
        );
    }

    /** @return iterable<string, array{?string}> */
    public static function notAcceptedTerms(): iterable
    {
        yield 'absentes' => [null];
        yield 'version périmée' => ['2025-01-01'];
    }

    #[DataProvider('notAcceptedTerms')]
    public function testANewAccountRequiresTheCurrentTermsOfUse(?string $acceptedVersion): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findOneByEmail')->willReturn(null);
        $userRepository->expects(self::never())->method('add');

        $this->expectException(TermsOfUseNotAcceptedException::class);

        $this->handler($userRepository, $this->createStub(CompanyRepositoryInterface::class))(
            new RegisterUserCommand('Jean Dupont', 'jean@exemple.fr', 'motdepasse123', acceptedTermsOfUseVersion: $acceptedVersion),
        );
    }

    public function testConvertsTheGuestWhoRegisters(): void
    {
        $guest = (new User('guest-uuid'))
            ->setRoles([User::ROLE_USER])
            ->setRegistrationDate(new \DateTimeImmutable('2026-11-01 08:00:00'))
            ->acceptTermsOfUse(self::TERMS, new \DateTimeImmutable('2026-11-01 08:00:00'));

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findOneByEmail')->willReturn(null);
        $userRepository->expects(self::once())->method('findOneByUuid')->with('guest-uuid')->willReturn($guest);
        $userRepository->expects(self::never())->method('add');

        // Un invité a accepté les conditions à l'ouverture de son compte : il n'a pas à les renvoyer
        $user = $this->handler($userRepository, $this->createStub(CompanyRepositoryInterface::class))(
            new RegisterUserCommand('Jean Dupont', 'jean@exemple.fr', 'motdepasse123', currentUserUuid: 'guest-uuid'),
        );

        // ID2 : même compte, donc même uuid, mêmes données et même session
        self::assertSame($guest, $user);
        self::assertSame('guest-uuid', $user->getUuid());
        self::assertFalse($user->isGuest());
        self::assertSame('jean@exemple.fr', $user->getEmail());
        self::assertSame('hashed:motdepasse123', $user->getPassword());
        self::assertEquals(new \DateTimeImmutable('2026-11-01 08:00:00'), $user->getRegistrationDate());
        self::assertSame(self::TERMS, $user->getTermsOfUseVersion());
    }

    public function testAFullAccountThatRegistersCreatesANewAccount(): void
    {
        $member = (new User('member-uuid'))->setEmail('membre@exemple.fr');

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findOneByEmail')->willReturn(null);
        $userRepository->method('findOneByUuid')->willReturn($member);
        $userRepository->expects(self::once())->method('add')->willReturnArgument(0);

        $user = $this->handler($userRepository, $this->createStub(CompanyRepositoryInterface::class))(new RegisterUserCommand(
            'Jean Dupont',
            'jean@exemple.fr',
            'motdepasse123',
            acceptedTermsOfUseVersion: self::TERMS,
            currentUserUuid: 'member-uuid',
        ));

        self::assertSame('user-uuid', $user->getUuid());
        self::assertSame('membre@exemple.fr', $member->getEmail());
    }

    private function handler(
        UserRepositoryInterface $userRepository,
        CompanyRepositoryInterface $companyRepository,
    ): RegisterUserCommandHandler {
        $idFactory = $this->createStub(IdFactoryInterface::class);
        $idFactory->method('make')->willReturn('user-uuid', 'company-uuid');

        $dateUtils = $this->createStub(DateUtilsInterface::class);
        $dateUtils->method('getNow')->willReturn(new \DateTimeImmutable('2026-11-15 10:00:00'));

        $stringUtils = $this->createStub(StringUtilsInterface::class);
        $stringUtils->method('normalizeEmail')->willReturnCallback(
            static fn (string $email): string => strtolower(trim($email)),
        );

        $passwordHasher = $this->createStub(PasswordHasherInterface::class);
        $passwordHasher->method('hash')->willReturnCallback(
            static fn (User $user, string $plainPassword): string => 'hashed:' . $plainPassword,
        );

        $termsOfUse = $this->createStub(TermsOfUseInterface::class);
        $termsOfUse->method('currentVersion')->willReturn(self::TERMS);

        return new RegisterUserCommandHandler(
            $userRepository,
            new IsEmailAlreadyExists($userRepository),
            new CompanyProvider($companyRepository, $idFactory),
            $termsOfUse,
            $idFactory,
            $dateUtils,
            $stringUtils,
            $passwordHasher,
        );
    }
}
