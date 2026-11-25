<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Domain\User\User;

interface UserRepositoryInterface
{
    public function add(User $user): User;

    public function remove(User $user): void;

    public function findOneByUuid(string $uuid): ?User;

    public function findOneByEmail(string $email): ?User;

    /**
     * Invités ouverts avant $registeredBefore qui n'ont plus aucune session valide à $now :
     * sans session ni identifiants, leurs données ne sont plus accessibles à personne (ID4).
     *
     * @return list<User>
     */
    public function findGuestsWithoutSession(\DateTimeImmutable $now, \DateTimeImmutable $registeredBefore): array;
}
