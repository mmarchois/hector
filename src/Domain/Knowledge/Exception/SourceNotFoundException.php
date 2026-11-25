<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Exception;

final class SourceNotFoundException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Source documentaire introuvable.');
    }
}
