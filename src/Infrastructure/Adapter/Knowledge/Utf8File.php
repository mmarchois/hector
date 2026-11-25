<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Knowledge;

/** Lecture d'un fichier texte : UTF-8 exigé, fins de ligne normalisées. */
final class Utf8File
{
    public static function read(string $path): string
    {
        $content = is_file($path) ? file_get_contents($path) : false;
        if ($content === false) {
            throw new \RuntimeException(\sprintf('Fichier introuvable ou illisible : %s', $path));
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            throw new \RuntimeException(\sprintf('Le fichier n\'est pas en UTF-8 : %s', $path));
        }

        // Marque d'ordre des octets éventuelle, et fins de ligne Windows ou anciennes
        return str_replace(["\r\n", "\r"], "\n", preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content);
    }
}
