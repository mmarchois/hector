<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Query;

use App\Application\User\Query\GetUserByUuidQuery;
use App\Application\User\Query\GetUserByUuidQueryHandler;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class GetUserByUuidQueryHandlerTest extends TestCase
{
    public function testReturnsTheUser(): void
    {
        $user = new User('user-uuid');
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('findOneByUuid')->with('user-uuid')->willReturn($user);

        self::assertSame($user, (new GetUserByUuidQueryHandler($userRepository))(new GetUserByUuidQuery('user-uuid')));
    }

    public function testThrowsWhenTheUserIsUnknown(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findOneByUuid')->willReturn(null);

        $this->expectException(UserNotFoundException::class);

        (new GetUserByUuidQueryHandler($userRepository))(new GetUserByUuidQuery('unknown-uuid'));
    }
}
