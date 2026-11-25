<?php

declare(strict_types=1);

namespace App\Application\Calculation\Port;

use App\Domain\Calculation\Refusal;

/**
 * Textes d'un calcul dans la langue du lecteur (décision D19). Le texte français du domaine
 * est la clé ; les nombres s'écrivent selon la langue. Sans langue précisée : celle de la
 * requête en cours.
 */
interface CalculationTextsInterface
{
    /**
     * @param array<string, string> $parameters valeurs des %marqueurs% du texte
     */
    public function text(string $french, array $parameters = [], ?string $locale = null): string;

    /** Le motif d'un refus, tel qu'il sera montré. */
    public function refusal(Refusal $refusal, ?string $locale = null): string;

    public function number(float $value, ?string $locale = null): string;
}
