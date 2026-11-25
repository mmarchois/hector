<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Knowledge;

use App\Application\Knowledge\Port\ExtractedPage;
use App\Application\Knowledge\Port\TextExtractorInterface;

/**
 * Markdown : le format des fiches rédigées pour Hector. Aucune transformation, donc aucune
 * perte : titres et tableaux arrivent tels quels au découpage. Le format n'a pas de pages.
 */
final class MarkdownExtractor implements TextExtractorInterface
{
    public function name(): string
    {
        return 'markdown@1';
    }

    public function supports(string $fileName): bool
    {
        return preg_match('/\.(md|markdown)$/i', $fileName) === 1;
    }

    public function extract(string $path): array
    {
        return [new ExtractedPage(null, Utf8File::read($path))];
    }
}
