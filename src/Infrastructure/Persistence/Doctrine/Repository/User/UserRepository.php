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

    public function findGuestsWithoutSession(\DateTimeImmutable $now, \DateTimeImmutable $registeredBefore): array
    {
        // En SQL : `refresh_tokens.username` (texte) porte l'uuid de l'utilisateur (colonne uuid)
        $uuids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT u.uuid FROM "user" u
             WHERE u.email IS NULL
               AND u.registration_date < :registeredBefore
               AND NOT EXISTS (
                   SELECT 1 FROM refresh_tokens rt
                   WHERE rt.username = u.uuid::text AND rt.valid > :now
               )',
            [
                'registeredBefore' => $registeredBefore->format(\DATE_ATOM),
                // `valid` est sans fuseau : gesdinet l'écrit dans le fuseau par défaut de PHP
                'now' => $now->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s'),
            ],
        );

        return $uuids === [] ? [] : $this->findBy(['uuid' => $uuids]);
    }
}
