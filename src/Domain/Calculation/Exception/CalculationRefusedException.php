<?php

declare(strict_types=1);

namespace App\Domain\Calculation\Exception;

use App\Domain\Calculation\Refusal;

/** Le calculateur refuse : entrée manquante, sans unité, d'unité incohérente ou hors bornes (CA2 à CA4). */
final class CalculationRefusedException extends \Exception
{
    /**
     * @param non-empty-list<Refusal> $refusals
     */
    public function __construct(
        public readonly array $refusals,
    ) {
        parent::__construct(implode(' ', array_map(static fn (Refusal $refusal): string => $refusal->message(), $refusals)));
    }
}
