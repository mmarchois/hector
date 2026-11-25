<?php

declare(strict_types=1);

namespace App\Application\User\Port;

/**
 * Données qu'un autre contexte rattache à un utilisateur par son uuid (conversations,
 * plus tard sessions de diagnostic…). Le contexte User ne les connaît pas : chaque
 * contexte fournit une implémentation, côté infrastructure, et elles sont toutes
 * appelées à la suppression d'un compte et à la fusion d'un invité (question P11).
 */
interface UserOwnedDataInterface
{
    /** Rattache à $toUserUuid tout ce qui appartient à $fromUserUuid. */
    public function transfer(string $fromUserUuid, string $toUserUuid): void;

    /** Efface tout ce qui appartient à l'utilisateur (droit à l'effacement). */
    public function erase(string $userUuid): void;
}
