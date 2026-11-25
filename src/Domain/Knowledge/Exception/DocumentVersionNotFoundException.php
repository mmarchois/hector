<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Exception;

final class DocumentVersionNotFoundException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Version de document introuvable.');
    }
}
