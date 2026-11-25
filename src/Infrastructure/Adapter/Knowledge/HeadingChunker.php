<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Knowledge;

use App\Application\Knowledge\Port\ChunkerInterface;
use App\Application\Knowledge\Port\ExtractedPage;
use App\Application\Knowledge\Port\PassageDraft;

/**
 * Découpe un texte en passages en suivant ses titres (syntaxe Markdown) et ses paragraphes.
 *
 * - Un passage ne chevauche ni deux sections ni deux pages : sa localisation est exacte (KN3).
 * - Un paragraphe ou un tableau n'est jamais coupé, même s'il dépasse la taille visée : un
 *   tableau coupé en deux ne veut plus rien dire.
 * - Aucun hasard, aucun état : mêmes pages, mêmes passages (KN6).
 */
final class HeadingChunker implements ChunkerInterface
{
    /** Taille visée d'un passage, en caractères. */
    public const int MAX_CHARACTERS = 1200;

    public function name(): string
    {
        return 'heading@1';
    }

    public function parameters(): array
    {
        return ['maxCharacters' => self::MAX_CHARACTERS];
    }

    public function chunk(array $pages): array
    {
        $passages = [];
        /** @var array<int, string> $headings titre en cours, par niveau */
        $headings = [];
        $article = null;
        $articleLevel = null;

        foreach ($pages as $page) {
            $buffer = [];
            foreach ($this->blocks($page->text) as $block) {
                if (preg_match('/^(#{1,6})\s+(.+?)\s*#*$/u', $block, $match) === 1) {
                    $this->flush($passages, $buffer, $page, $headings, $article);
                    $level = \strlen($match[1]);
                    $title = trim($match[2]);

                    // Un titre referme les titres de même niveau ou plus profonds
                    $headings = array_filter($headings, static fn (int $known): bool => $known < $level, \ARRAY_FILTER_USE_KEY);
                    $headings[$level] = $title;
                    if ($articleLevel !== null && $level <= $articleLevel) {
                        $article = null;
                        $articleLevel = null;
                    }
                    if (preg_match('/^(?:Article|Art\.)\s+([\w.\-]+)/iu', $title, $found) === 1) {
                        $article = $found[1];
                        $articleLevel = $level;
                    }

                    continue;
                }

                $length = mb_strlen(implode("\n\n", $buffer));
                if ($buffer !== [] && $length + 2 + mb_strlen($block) > self::MAX_CHARACTERS) {
                    $this->flush($passages, $buffer, $page, $headings, $article);
                }
                $buffer[] = $block;
            }
            $this->flush($passages, $buffer, $page, $headings, $article);
        }

        return $passages;
    }

    /**
     * @param list<PassageDraft> $passages
     * @param list<string>       $buffer
     * @param array<int, string> $headings
     */
    private function flush(array &$passages, array &$buffer, ExtractedPage $page, array $headings, ?string $article): void
    {
        $text = trim(implode("\n\n", $buffer));
        $buffer = [];
        if ($text === '') {
            return;
        }

        ksort($headings);
        $passages[] = new PassageDraft($page->number, $headings === [] ? null : implode(' > ', $headings), $article, $text);
    }

    /**
     * Blocs séparés par une ligne vide ; un titre est un bloc à lui seul.
     *
     * @return list<string>
     */
    private function blocks(string $text): array
    {
        $blocks = [];
        $current = [];
        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $text)) as $line) {
            $heading = preg_match('/^#{1,6}\s+\S/', $line) === 1;
            if (trim($line) === '' || $heading) {
                if ($current !== []) {
                    $blocks[] = implode("\n", $current);
                    $current = [];
                }
                if ($heading) {
                    $blocks[] = rtrim($line);
                }

                continue;
            }
            $current[] = rtrim($line);
        }
        if ($current !== []) {
            $blocks[] = implode("\n", $current);
        }

        return $blocks;
    }
}
