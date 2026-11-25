<?php

declare(strict_types=1);

namespace App\Domain\Calculation\Exception;

final class CalculatorNotFoundException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Calculateur inconnu.');
    }
}
