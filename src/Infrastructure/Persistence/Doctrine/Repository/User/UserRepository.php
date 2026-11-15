<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository\User;

use App\Application\StringUtilsInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
final class UserRepository extends ServiceEntityRepository implements UserRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly StringUtilsInterface $stringUtils,
    ) {
        parent::__construct($registry, User::class);
    }

    public function add(User $user): User
    {
        $this->getEntityManager()->persist($user);

        return $user;
    }

    public function remove(User $user): void
    {
        $this->getEntityManager()->remove($user);
    }

    public function findOneByUuid(string $uuid): ?User
    {
        return $this->find($uuid);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $this->stringUtils->normalizeEmail($email)]);
    }
}
