<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Command;

use App\Application\User\Command\DeleteUserCommand;
use App\Application\User\Command\DeleteUserCommandHandler;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\RefreshTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class DeleteUserCommandHandlerTest extends TestCase
{
    public function testRevokesSessionsThenRemovesTheUser(): void
    {
        $user = new User('user-uuid');

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('findOneByUuid')->with('user-uuid')->willReturn($user);
        $userRepository->expects(self::once())->method('remove')->with($user);

        $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $refreshTokenRepository->expects(self::once())->method('removeAllByUser')->with('user-uuid');

        (new DeleteUserCommandHandler($userRepository, $refreshTokenRepository))(new DeleteUserCommand('user-uuid'));
    }

    public function testThrowsWhenTheUserIsUnknown(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findOneByUuid')->willReturn(null);
        $userRepository->expects(self::never())->method('remove');

        $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $refreshTokenRepository->expects(self::never())->method('removeAllByUser');

        $this->expectException(UserNotFoundException::class);

        (new DeleteUserCommandHandler($userRepository, $refreshTokenRepository))(new DeleteUserCommand('unknown-uuid'));
    }
}
