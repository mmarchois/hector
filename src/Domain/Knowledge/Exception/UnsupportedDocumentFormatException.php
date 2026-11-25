<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Exception;

final class UnsupportedDocumentFormatException extends \Exception
{
    public function __construct(string $fileName)
    {
        parent::__construct(\sprintf('Aucune extraction ne sait lire « %s ».', $fileName));
    }
}
