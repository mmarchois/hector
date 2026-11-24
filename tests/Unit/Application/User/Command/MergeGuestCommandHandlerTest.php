<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Command;

use App\Application\User\Command\MergeGuestCommand;
use App\Application\User\Command\MergeGuestCommandHandler;
use App\Application\User\Port\UserOwnedDataInterface;
use App\Domain\User\Exception\InvalidGuestSessionException;
use App\Domain\User\Repository\RefreshTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Question P11 : un invité qui se connecte à son compte y retrouve ses données. */
final class MergeGuestCommandHandlerTest extends TestCase
{
    public function testTransfersTheGuestDataThenDeletesTheGuest(): void
    {
        $guest = new User('guest-uuid');

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findOneByUuid')->willReturn($guest);
        $userRepository->expects(self::once())->method('remove')->with($guest);

        $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $refreshTokenRepository->expects(self::once())->method('findUserUuidOfValidToken')->with('guest-token')->willReturn('guest-uuid');
        $refreshTokenRepository->expects(self::once())->method('removeAllByUser')->with('guest-uuid');

        $conversations = $this->createMock(UserOwnedDataInterface::class);
        $conversations->expects(self::once())->method('transfer')->with('guest-uuid', 'account-uuid');

        (new MergeGuestCommandHandler($userRepository, $refreshTokenRepository, [$conversations]))(
            new MergeGuestCommand('account-uuid', 'guest-token'),
        );
    }

    /** @return iterable<string, array{?string, ?User}> */
    public static function invalidSessions(): iterable
    {
        yield 'jeton inconnu ou expiré' => [null, null];
        yield 'compte complet, pas un invité' => ['member-uuid', (new User('member-uuid'))->setEmail('membre@exemple.fr')];
        yield 'le compte lui-même' => ['account-uuid', new User('account-uuid')];
    }

    #[DataProvider('invalidSessions')]
    public function testOnlyAValidGuestSessionIsMerged(?string $tokenOwner, ?User $owner): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findOneByUuid')->willReturn($owner);
        $userRepository->expects(self::never())->method('remove');

        $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $refreshTokenRepository->method('findUserUuidOfValidToken')->willReturn($tokenOwner);
        $refreshTokenRepository->expects(self::never())->method('removeAllByUser');

        $ownedData = $this->createMock(UserOwnedDataInterface::class);
        $ownedData->expects(self::never())->method('transfer');

        $this->expectException(InvalidGuestSessionException::class);

        (new MergeGuestCommandHandler($userRepository, $refreshTokenRepository, [$ownedData]))(
            new MergeGuestCommand('account-uuid', 'token'),
        );
    }
}
