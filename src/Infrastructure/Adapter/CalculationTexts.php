<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Application\Calculation\Port\CalculationTextsInterface;
use App\Domain\Calculation\Refusal;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Textes d'un calcul traduits par le traducteur de Symfony (translations/messages.<langue>.yaml),
 * nombres écrits selon la langue : 1 000 en français, 1,000 en anglais, 1.000 en allemand.
 */
final readonly class CalculationTexts implements CalculationTextsInterface
{
    /** Assez de décimales pour ne rien arrondir de plus que le calcul. */
    private const int MAX_DECIMALS = 12;

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function text(string $french, array $parameters = [], ?string $locale = null): string
    {
        return $this->translator->trans($french, $parameters, locale: $locale);
    }

    public function refusal(Refusal $refusal, ?string $locale = null): string
    {
        $parameters = [];
        foreach ($refusal->parameters as $marker => $value) {
            $parameters[$marker] = \is_float($value) ? $this->number($value, $locale) : $value;
        }
        if ($refusal->label !== null) {
            $parameters['%input%'] = $this->text($refusal->label, [], $locale);
        }

        return $this->text($refusal->template, $parameters, $locale);
    }

    public function number(float $value, ?string $locale = null): string
    {
        $formatter = new \NumberFormatter($locale ?? $this->translator->getLocale(), \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, self::MAX_DECIMALS);

        return (string) $formatter->format($value);
    }
}
