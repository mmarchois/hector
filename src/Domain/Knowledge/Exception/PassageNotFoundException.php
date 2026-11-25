<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Exception;

final class PassageNotFoundException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Passage introuvable.');
    }
}
