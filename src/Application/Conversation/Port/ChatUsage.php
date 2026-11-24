<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

/** Consommation d'un appel, en jetons. */
final readonly class ChatUsage
{
    public function __construct(
        public int $inputTokens,
        public int $outputTokens,
        public int $cacheReadInputTokens = 0,
        public int $cacheCreationInputTokens = 0,
    ) {
    }
}
