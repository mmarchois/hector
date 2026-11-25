<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Application\User\Port\TermsOfUseInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Conditions d'utilisation versionnées dans le dépôt (décision D13). Changer le texte
 * impose de changer la version : chacun devra accepter la nouvelle.
 */
final readonly class TermsOfUse implements TermsOfUseInterface
{
    public function __construct(
        #[Autowire('%app.terms_of_use.version%')]
        private string $version,
        #[Autowire('%kernel.project_dir%/resources/legal/terms-of-use.md')]
        private string $path,
    ) {
    }

    public function currentVersion(): string
    {
        return $this->version;
    }

    public function content(): string
    {
        $content = file_get_contents($this->path);
        if ($content === false) {
            throw new \RuntimeException(\sprintf('Conditions d\'utilisation introuvables : %s', $this->path));
        }

        return $content;
    }
}
