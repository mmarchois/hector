<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Knowledge;

use App\Application\Knowledge\Port\ExtractedPage;
use App\Application\Knowledge\Port\TextExtractorInterface;

/**
 * Texte brut. Un saut de page (caractère « form feed ») sépare les pages, comme dans la
 * sortie des outils d'extraction de PDF : chaque passage garde ainsi sa page d'origine (KN3).
 */
final class PlainTextExtractor implements TextExtractorInterface
{
    public function name(): string
    {
        return 'text@1';
    }

    public function supports(string $fileName): bool
    {
        return preg_match('/\.txt$/i', $fileName) === 1;
    }

    public function extract(string $path): array
    {
        $text = Utf8File::read($path);
        if (!str_contains($text, "\f")) {
            return [new ExtractedPage(null, $text)];
        }

        $pages = [];
        foreach (explode("\f", $text) as $index => $page) {
            // Une page vide garde son numéro : celui des suivantes ne doit pas glisser
            $pages[] = new ExtractedPage($index + 1, $page);
        }

        return $pages;
    }
}
