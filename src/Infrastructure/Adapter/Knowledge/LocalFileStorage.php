<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Knowledge;

use App\Application\Knowledge\Port\FileStorageInterface;
use App\Application\Knowledge\Port\StoredFile;

/**
 * Fichiers d'origine sur le système de fichiers (décision D10), nommés par leur empreinte :
 * un même fichier n'est conservé qu'une fois, et son nom dit s'il a été altéré.
 */
final readonly class LocalFileStorage implements FileStorageInterface
{
    public function __construct(
        private string $directory,
    ) {
    }

    public function store(string $path): StoredFile
    {
        $fingerprint = is_file($path) ? hash_file('sha256', $path) : false;
        if ($fingerprint === false) {
            throw new \RuntimeException(\sprintf('Fichier introuvable ou illisible : %s', $path));
        }

        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));
        $key = $fingerprint . ($extension !== '' && preg_match('/^[a-z0-9]+$/', $extension) === 1 ? '.' . $extension : '');

        if (!is_dir($this->directory) && !mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException(\sprintf('Impossible de créer %s.', $this->directory));
        }
        $target = $this->directory . '/' . $key;
        if (!is_file($target) && !copy($path, $target)) {
            throw new \RuntimeException(\sprintf('Impossible de conserver %s.', $path));
        }

        return new StoredFile($key, $fingerprint);
    }

    public function path(string $key): string
    {
        // Une clé est une empreinte et une extension : jamais un chemin
        if (preg_match('/^[a-f0-9]{64}(\.[a-z0-9]+)?$/', $key) !== 1) {
            throw new \InvalidArgumentException('Clé de stockage invalide.');
        }

        return $this->directory . '/' . $key;
    }
}
