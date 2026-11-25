<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Calculation\Port\CalculationTextsInterface;
use App\Domain\Calculation\Refusal;

/** Textes d'un calcul en français, sans traducteur : pour les tests unitaires. */
final class FrenchCalculationTexts implements CalculationTextsInterface
{
    public function text(string $french, array $parameters = [], ?string $locale = null): string
    {
        return strtr($french, $parameters);
    }

    public function refusal(Refusal $refusal, ?string $locale = null): string
    {
        return $refusal->message();
    }

    public function number(float $value, ?string $locale = null): string
    {
        $text = number_format($value, 6, ',', "\u{202F}");

        return rtrim(rtrim($text, '0'), ',');
    }
}
