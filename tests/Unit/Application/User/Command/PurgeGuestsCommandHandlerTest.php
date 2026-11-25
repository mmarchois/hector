<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Command;

use App\Application\User\Command\PurgeGuestsCommand;
use App\Application\User\Command\PurgeGuestsCommandHandler;
use App\Application\User\Port\UserOwnedDataInterface;
use App\Domain\Port\DateUtilsInterface;
use App\Domain\User\Repository\RefreshTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class PurgeGuestsCommandHandlerTest extends TestCase
{
    public function testDeletesTheGuestsWithoutSessionAndTheirTokens(): void
    {
        $now = new \DateTimeImmutable('2026-11-24 03:00:00');
        $guests = [new User('guest-1'), new User('guest-2')];

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        // Un invité ouvert il y a moins d'un jour n'est jamais emporté
        $userRepository->expects(self::once())->method('findGuestsWithoutSession')
            ->with($now, new \DateTimeImmutable('2026-11-23 03:00:00'))
            ->willReturn($guests);
        $removed = [];
        $userRepository->expects(self::exactly(2))->method('remove')
            ->willReturnCallback(static function (User $user) use (&$removed): void {
                $removed[] = $user->getUuid();
            });

        $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $revoked = [];
        $refreshTokenRepository->expects(self::exactly(2))->method('removeAllByUser')
            ->willReturnCallback(static function (string $uuid) use (&$revoked): void {
                $revoked[] = $uuid;
            });

        $dateUtils = $this->createStub(DateUtilsInterface::class);
        $dateUtils->method('getNow')->willReturn($now);

        $ownedData = $this->createMock(UserOwnedDataInterface::class);
        $erased = [];
        $ownedData->expects(self::exactly(2))->method('erase')
            ->willReturnCallback(static function (string $uuid) use (&$erased): void {
                $erased[] = $uuid;
            });

        $count = (new PurgeGuestsCommandHandler($userRepository, $refreshTokenRepository, $dateUtils, [$ownedData]))(new PurgeGuestsCommand());

        self::assertSame(2, $count);
        self::assertSame(['guest-1', 'guest-2'], $removed);
        self::assertSame(['guest-1', 'guest-2'], $revoked);
        self::assertSame(['guest-1', 'guest-2'], $erased);
    }
}
