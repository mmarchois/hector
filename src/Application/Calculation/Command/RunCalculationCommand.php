<?php

declare(strict_types=1);

namespace App\Application\Calculation\Command;

use App\Application\CommandInterface;

final readonly class RunCalculationCommand implements CommandInterface
{
    /**
     * @param array<mixed>|null $inputs par clé d'entrée, une valeur et son unité ; le calculateur
     *                                  les contrôle et refuse en disant pourquoi
     */
    public function __construct(
        public string $ownerUuid,
        public ?string $calculator,
        public ?array $inputs,
    ) {
    }
}
