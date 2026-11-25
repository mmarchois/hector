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

    public function content(string $locale): string
    {
        // resources/legal/terms-of-use.<langue>.md, sinon le texte français
        $translated = preg_replace('/\.md$/', '.' . $locale . '.md', $this->path);
        $path = preg_match('/^[a-z]{2}$/', $locale) === 1 && \is_string($translated) && is_file($translated) ? $translated : $this->path;

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException(\sprintf('Conditions d\'utilisation introuvables : %s', $path));
        }

        return $content;
    }
}
