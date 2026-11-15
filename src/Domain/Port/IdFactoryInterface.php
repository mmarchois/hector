<?php

declare(strict_types=1);

namespace App\Domain\Port;

interface IdFactoryInterface
{
    public function make(): string;
}
