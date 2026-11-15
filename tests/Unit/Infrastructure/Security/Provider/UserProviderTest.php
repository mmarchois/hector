<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security\Provider;

use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;
use App\Infrastructure\Security\Provider\UserProvider;
use App\Infrastructure\Security\User\SecurityUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

final class UserProviderTest extends TestCase
{
    private const string UUID = '0b507871-8b5e-4575-b297-a630310fc06e';

    public function testLoadsByEmailAtLogin(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('findOneByEmail')->with('jean@exemple.fr')->willReturn($this->user());
        $userRepository->expects(self::never())->method('findOneByUuid');

        $securityUser = (new UserProvider($userRepository))->loadUserByIdentifier('jean@exemple.fr');

        self::assertInstanceOf(SecurityUser::class, $securityUser);
        // L'identifiant porté par les tokens est l'uuid, stable si l'email change
        self::assertSame(self::UUID, $securityUser->getUserIdentifier());
        self::assertSame('jean@exemple.fr', $securityUser->getEmail());
        self::assertSame([User::ROLE_USER], $securityUser->getRoles());
        self::assertSame('hashed', $securityUser->getPassword());
    }

    public function testLoadsByUuidFromAToken(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('findOneByUuid')->with(self::UUID)->willReturn($this->user());
        $userRepository->expects(self::never())->method('findOneByEmail');

        $securityUser = (new UserProvider($userRepository))->loadUserByIdentifier(self::UUID);

        self::assertSame(self::UUID, $securityUser->getUserIdentifier());
    }

    public function testThrowsWhenTheUserIsUnknown(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findOneByEmail')->willReturn(null);

        $this->expectException(UserNotFoundException::class);

        (new UserProvider($userRepository))->loadUserByIdentifier('inconnu@exemple.fr');
    }

    private function user(): User
    {
        return (new User(self::UUID))
            ->setFullName('Jean Dupont')
            ->setEmail('jean@exemple.fr')
            ->setRoles([User::ROLE_USER])
            ->setPassword('hashed');
    }
}
