<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Application\Conversation\Port\Prompt;
use App\Application\Conversation\Port\PromptCatalogInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Consignes versionnées dans le dépôt : resources/prompts/<nom>.md (décision D13). */
final readonly class FilePromptCatalog implements PromptCatalogInterface
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/resources/prompts')]
        private string $directory,
    ) {
    }

    public function get(string $name): Prompt
    {
        if (preg_match('/^[a-z0-9-]+$/', $name) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Nom de consigne invalide : %s', $name));
        }

        $text = file_get_contents(\sprintf('%s/%s.md', $this->directory, $name));
        if ($text === false) {
            throw new \RuntimeException(\sprintf('Consigne introuvable : %s', $name));
        }

        return new Prompt($name, substr(hash('sha256', $text), 0, 12), trim($text));
    }
}
