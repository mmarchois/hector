<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Command;

use App\Application\Company\Service\CompanyProvider;
use App\Application\StringUtilsInterface;
use App\Application\User\Command\SaveProfileCommand;
use App\Application\User\Command\SaveProfileCommandHandler;
use App\Domain\Company\Company;
use App\Domain\Company\Repository\CompanyRepositoryInterface;
use App\Domain\Port\IdFactoryInterface;
use App\Domain\Port\PasswordHasherInterface;
use App\Domain\User\Exception\EmailAlreadyExistsException;
use App\Domain\User\Exception\GuestAccountException;
use App\Domain\User\Exception\InvalidCurrentPasswordException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\RefreshTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Specification\IsEmailAlreadyExists;
use App\Domain\User\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class SaveProfileCommandHandlerTest extends TestCase
{
    private const string USER_UUID = 'user-uuid';

    private User $user;
    private Company $company;
    private Stub&UserRepositoryInterface $userRepository;
    private Stub&CompanyRepositoryInterface $companyRepository;
    private MockObject&RefreshTokenRepositoryInterface $refreshTokenRepository;

    protected function setUp(): void
    {
        $this->company = (new Company('company-uuid'))
            ->setName('Ohm Élec')
            ->setSiret('12345678901234')
            ->setVatNumber('FR12345678901');

        $this->user = (new User(self::USER_UUID))
            ->setFullName('Marie Ohm')
            ->setEmail('marie@exemple.fr')
            ->setRoles([User::ROLE_USER])
            ->setProfessional(true)
            ->setCompany($this->company)
            ->setPassword('hashed:ancien-mot-de-passe');

        $this->userRepository = $this->createStub(UserRepositoryInterface::class);
        $this->userRepository->method('findOneByUuid')->willReturnCallback(
            fn (string $uuid): ?User => $uuid === self::USER_UUID ? $this->user : null,
        );

        $this->companyRepository = $this->createStub(CompanyRepositoryInterface::class);
        $this->companyRepository->method('findOneBySiret')->willReturnCallback(
            fn (string $siret): ?Company => $siret === $this->company->getSiret() ? $this->company : null,
        );

        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
    }

    public function testUpdatesIdentityAndOwnCompany(): void
    {
        $this->refreshTokenRepository->expects(self::never())->method('removeAllByUser');

        $user = $this->handler()(new SaveProfileCommand(
            userUuid: self::USER_UUID,
            fullName: ' Marie Ohm-Volt ',
            email: 'Marie.Volt@Exemple.fr',
            professional: true,
            companyName: 'Ohm & Volt Élec',
            siret: '12345678901234',
            vatNumber: 'FR98765432109',
        ));

        self::assertSame($this->user, $user);
        self::assertSame('Marie Ohm-Volt', $user->getFullName());
        self::assertSame('marie.volt@exemple.fr', $user->getEmail());
        self::assertSame($this->company, $user->getCompany());
        self::assertSame('Ohm & Volt Élec', $this->company->getName());
        self::assertSame('FR98765432109', $this->company->getVatNumber());
        self::assertSame('hashed:ancien-mot-de-passe', $user->getPassword());
    }

    public function testAGuestHasNoProfileToSave(): void
    {
        // ID1 : un invité n'a ni email ni mot de passe, il doit d'abord créer un compte
        $this->user->setEmail(null)->setFullName(null)->setPassword(null);
        $this->refreshTokenRepository->expects(self::never())->method('removeAllByUser');

        try {
            $this->handler()($this->command(email: 'invite@exemple.fr'));
            self::fail('Une GuestAccountException était attendue.');
        } catch (GuestAccountException) {
            self::assertTrue($this->user->isGuest());
        }
    }

    public function testRejectsTheEmailOfAnotherAccount(): void
    {
        $this->userRepository->method('findOneByEmail')->willReturn(new User('other-uuid'));
        $this->refreshTokenRepository->expects(self::never())->method('removeAllByUser');

        try {
            $this->handler()($this->command(email: 'pris@exemple.fr'));
            self::fail('Une EmailAlreadyExistsException était attendue.');
        } catch (EmailAlreadyExistsException) {
            self::assertSame('marie@exemple.fr', $this->user->getEmail());
        }
    }

    public function testKeepingItsOwnEmailIsNotAConflict(): void
    {
        // Le compte lui-même porte cet email : aucune recherche de doublon ne doit le bloquer
        $this->userRepository->method('findOneByEmail')->willReturn($this->user);
        $this->refreshTokenRepository->expects(self::never())->method('removeAllByUser');

        $user = $this->handler()($this->command(email: 'MARIE@exemple.fr'));

        self::assertSame('marie@exemple.fr', $user->getEmail());
    }

    public function testBecomingAnIndividualDetachesTheCompany(): void
    {
        $this->refreshTokenRepository->expects(self::never())->method('removeAllByUser');

        $user = $this->handler()(new SaveProfileCommand(
            userUuid: self::USER_UUID,
            fullName: 'Marie Ohm',
            email: 'marie@exemple.fr',
            professional: false,
        ));

        self::assertFalse($user->isProfessional());
        self::assertNull($user->getCompany());
    }

    public function testJoiningAnotherCompanyDoesNotRewriteIt(): void
    {
        $other = (new Company('other-company-uuid'))
            ->setName('Volt Frères')
            ->setSiret('99999999999999')
            ->setVatNumber('FR11111111111');
        $companyRepository = $this->createStub(CompanyRepositoryInterface::class);
        $companyRepository->method('findOneBySiret')->willReturn($other);
        $this->refreshTokenRepository->expects(self::never())->method('removeAllByUser');

        $user = $this->handler($companyRepository)(new SaveProfileCommand(
            userUuid: self::USER_UUID,
            fullName: 'Marie Ohm',
            email: 'marie@exemple.fr',
            professional: true,
            companyName: 'Nom saisi',
            siret: '99999999999999',
            vatNumber: 'FR22222222222',
        ));

        self::assertSame($other, $user->getCompany());
        self::assertSame('Volt Frères', $other->getName());
        self::assertSame('FR11111111111', $other->getVatNumber());
        // L'ancienne entreprise reste intacte pour ses autres membres
        self::assertSame('Ohm Élec', $this->company->getName());
    }

    public function testChangesThePasswordAndRevokesMobileSessions(): void
    {
        $this->refreshTokenRepository->expects(self::once())->method('removeAllByUser')->with(self::USER_UUID);

        $user = $this->handler()($this->command(
            currentPassword: 'ancien-mot-de-passe',
            newPassword: 'nouveau-mot-de-passe',
        ));

        self::assertSame('hashed:nouveau-mot-de-passe', $user->getPassword());
    }

    public function testAWrongCurrentPasswordChangesNothing(): void
    {
        $this->refreshTokenRepository->expects(self::never())->method('removeAllByUser');

        try {
            $this->handler()($this->command(
                fullName: 'Autre Nom',
                currentPassword: 'mauvais',
                newPassword: 'nouveau-mot-de-passe',
            ));
            self::fail('Une InvalidCurrentPasswordException était attendue.');
        } catch (InvalidCurrentPasswordException) {
            self::assertSame('hashed:ancien-mot-de-passe', $this->user->getPassword());
            self::assertSame('Marie Ohm', $this->user->getFullName());
        }
    }

    public function testANewPasswordWithoutTheCurrentOneIsRejected(): void
    {
        $this->refreshTokenRepository->expects(self::never())->method('removeAllByUser');

        $this->expectException(InvalidCurrentPasswordException::class);

        $this->handler()($this->command(newPassword: 'nouveau-mot-de-passe'));
    }

    public function testThrowsWhenTheUserIsUnknown(): void
    {
        $this->refreshTokenRepository->expects(self::never())->method('removeAllByUser');

        $this->expectException(UserNotFoundException::class);

        $this->handler()(new SaveProfileCommand('unknown-uuid', 'Marie Ohm', 'marie@exemple.fr'));
    }

    private function command(
        string $fullName = 'Marie Ohm',
        string $email = 'marie@exemple.fr',
        ?string $currentPassword = null,
        ?string $newPassword = null,
    ): SaveProfileCommand {
        return new SaveProfileCommand(
            userUuid: self::USER_UUID,
            fullName: $fullName,
            email: $email,
            professional: true,
            companyName: 'Ohm Élec',
            siret: '12345678901234',
            vatNumber: 'FR12345678901',
            currentPassword: $currentPassword,
            newPassword: $newPassword,
        );
    }

    private function handler(?CompanyRepositoryInterface $companyRepository = null): SaveProfileCommandHandler
    {
        $stringUtils = $this->createStub(StringUtilsInterface::class);
        $stringUtils->method('normalizeEmail')->willReturnCallback(
            static fn (string $email): string => strtolower(trim($email)),
        );

        $passwordHasher = $this->createStub(PasswordHasherInterface::class);
        $passwordHasher->method('hash')->willReturnCallback(
            static fn (User $user, string $plainPassword): string => 'hashed:' . $plainPassword,
        );
        $passwordHasher->method('isValid')->willReturnCallback(
            static fn (User $user, string $plainPassword): bool => $user->getPassword() === 'hashed:' . $plainPassword,
        );

        return new SaveProfileCommandHandler(
            $this->userRepository,
            $this->refreshTokenRepository,
            new IsEmailAlreadyExists($this->userRepository),
            new CompanyProvider($companyRepository ?? $this->companyRepository, $this->createStub(IdFactoryInterface::class)),
            $stringUtils,
            $passwordHasher,
        );
    }
}
