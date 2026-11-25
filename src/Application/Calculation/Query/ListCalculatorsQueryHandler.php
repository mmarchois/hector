<?php

declare(strict_types=1);

namespace App\Application\Calculation\Query;

use App\Domain\Calculation\CalculatorCatalog;
use App\Domain\Calculation\CalculatorDefinition;
use App\Domain\Calculation\CalculatorInterface;

final readonly class ListCalculatorsQueryHandler
{
    public function __construct(
        private CalculatorCatalog $catalog,
    ) {
    }

    /** @return list<CalculatorDefinition> */
    public function __invoke(ListCalculatorsQuery $query): array
    {
        return array_map(static fn (CalculatorInterface $calculator): CalculatorDefinition => $calculator->definition(), $this->catalog->all());
    }
}
