<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Domain\Calculation\Refusal;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Textes d'un calcul dans la langue du lecteur : le texte français du domaine est la clé de
 * traduction, et les nombres s'écrivent selon la langue (1 000 en français, 1,000 en anglais).
 */
final readonly class CalculationTexts
{
    /** Assez de décimales pour ne rien arrondir de plus que le calcul. */
    private const int MAX_DECIMALS = 12;

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /** Le motif d'un refus ; dans la langue de la requête en cours si aucune n'est précisée. */
    public function refusal(Refusal $refusal, ?string $locale = null): string
    {
        $locale ??= $this->translator->getLocale();

        $parameters = [];
        foreach ($refusal->parameters as $marker => $value) {
            $parameters[$marker] = \is_float($value) ? $this->number($value, $locale) : $value;
        }
        if ($refusal->label !== null) {
            $parameters['%input%'] = $this->translator->trans($refusal->label, locale: $locale);
        }

        return $this->translator->trans($refusal->template, $parameters, locale: $locale);
    }

    public function number(float $value, string $locale): string
    {
        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, self::MAX_DECIMALS);

        return (string) $formatter->format($value);
    }
}
