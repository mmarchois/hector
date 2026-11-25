<?php

declare(strict_types=1);

namespace App\Domain\Calculation;

/** Une valeur et son unité : aucun nombre sans unité ne circule (CA2). */
final readonly class Quantity
{
    public function __construct(
        public float $value,
        public Unit $unit,
    ) {
        if (!is_finite($value)) {
            throw new \InvalidArgumentException('Une grandeur a une valeur finie.');
        }
    }

    /** Même grandeur dans une autre unité de la même dimension. */
    public function to(Unit $unit): self
    {
        if ($unit->dimension() !== $this->unit->dimension()) {
            throw new \InvalidArgumentException(\sprintf('On ne convertit pas une grandeur « %s » en « %s ».', $this->unit->dimension()->label(), $unit->dimension()->label()));
        }
        if ($unit === $this->unit) {
            return $this;
        }

        return new self($this->value * $this->unit->factor() / $unit->factor(), $unit);
    }
}
