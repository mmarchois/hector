<?php

declare(strict_types=1);

namespace App\Domain\Calculation\Exception;

final class CalculationRunNotFoundException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Calcul introuvable.');
    }
}
