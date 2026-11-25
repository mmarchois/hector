<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

/** Outil décrit au modèle : nom, mode d'emploi, schéma JSON de son entrée. */
final readonly class ChatTool
{
    /**
     * @param array<string, mixed> $inputSchema
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema,
    ) {
    }
}
