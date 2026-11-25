<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

final readonly class Participant
{
    public function __construct(
        public bool $guest,
        public bool $professional,
    ) {
    }
}
