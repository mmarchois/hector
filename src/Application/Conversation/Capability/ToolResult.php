<?php

declare(strict_types=1);

namespace App\Application\Conversation\Capability;

final readonly class ToolResult
{
    public function __construct(
        /** Ce que le modèle lit en retour de son appel */
        public string $content,
        public bool $error = false,
    ) {
    }
}
