<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Port;

/** Conservation des fichiers d'origine (décision D10) : système de fichiers aujourd'hui, stockage d'objets demain. */
interface FileStorageInterface
{
    /** Conserve une copie du fichier et en calcule l'empreinte. */
    public function store(string $path): StoredFile;

    /** Chemin lisible du fichier conservé sous cette clé. */
    public function path(string $key): string;
}
