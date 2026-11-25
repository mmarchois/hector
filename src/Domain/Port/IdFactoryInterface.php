<?php

declare(strict_types=1);

namespace App\Domain\Port;

interface IdFactoryInterface
{
    /**
     * Nouvel uuid, croissant dans le temps (UUID v7) : comparer deux identifiants donne
     * leur ordre de création (ordre des messages d'une conversation).
     */
    public function make(): string;
}
