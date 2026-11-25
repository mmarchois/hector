<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Command;

use App\Application\User\Command\CreateGuestCommand;
use App\Application\User\Command\CreateGuestCommandHandler;
use App\Application\User\Port\TermsOfUseInterface;
use App\Domain\Port\DateUtilsInterface;
use App\Domain\Port\IdFactoryInterface;
use App\Domain\User\Exception\TermsOfUseNotAcceptedException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class CreateGuestCommandHandlerTest extends TestCase
{
    private const string TERMS = '2026-11-24';

    public function testOpensAGuestAccountWithoutIdentity(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('add')->willReturnArgument(0);

        $guest = $this->handler($userRepository)(new CreateGuestCommand(self::TERMS));

        // ID1 : ni nom, ni email, ni mot de passe
        self::assertSame('guest-uuid', $guest->getUuid());
        self::assertTrue($guest->isGuest());
        self::assertNull($guest->getFullName());
        self::assertNull($guest->getEmail());
        self::assertNull($guest->getPassword());
        self::assertSame([User::ROLE_USER], $guest->getRoles());
        // ID3 : la qualification la plus restrictive
        self::assertFalse($guest->isProfessional());
        self::assertNull($guest->getCompany());
        self::assertEquals(new \DateTimeImmutable('2026-11-24 09:00:00'), $guest->getRegistrationDate());
        self::assertSame(self::TERMS, $guest->getTermsOfUseVersion());
        self::assertEquals(new \DateTimeImmutable('2026-11-24 09:00:00'), $guest->getTermsOfUseAcceptedAt());
    }

    public function testRequiresTheCurrentTermsOfUse(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::never())->method('add');

        $this->expectException(TermsOfUseNotAcceptedException::class);

        $this->handler($userRepository)(new CreateGuestCommand('2025-01-01'));
    }

    private function handler(UserRepositoryInterface $userRepository): CreateGuestCommandHandler
    {
        $termsOfUse = $this->createStub(TermsOfUseInterface::class);
        $termsOfUse->method('currentVersion')->willReturn(self::TERMS);

        $idFactory = $this->createStub(IdFactoryInterface::class);
        $idFactory->method('make')->willReturn('guest-uuid');

        $dateUtils = $this->createStub(DateUtilsInterface::class);
        $dateUtils->method('getNow')->willReturn(new \DateTimeImmutable('2026-11-24 09:00:00'));

        return new CreateGuestCommandHandler($userRepository, $termsOfUse, $idFactory, $dateUtils);
    }
}
