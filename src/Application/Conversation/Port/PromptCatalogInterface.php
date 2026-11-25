<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

/** Consignes versionnées (resources/prompts, décision D13). */
interface PromptCatalogInterface
{
    public function get(string $name): Prompt;
}
