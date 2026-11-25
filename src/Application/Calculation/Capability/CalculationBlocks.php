<?php

declare(strict_types=1);

namespace App\Application\Calculation\Capability;

use App\Application\Calculation\Port\CalculationTextsInterface;
use App\Application\Calculation\Service\CalculationPresenter;
use App\Domain\Calculation\CalculationResult;
use App\Domain\Calculation\CalculatorDefinition;
use App\Domain\Calculation\Refusal;
use App\Domain\Conversation\Block;

/**
 * Blocs qu'un calcul donne à montrer dans une conversation, composés par le serveur à partir
 * de l'exécution : le modèle n'en écrit aucun chiffre ni aucun motif.
 */
final readonly class CalculationBlocks
{
    /** Calcul exécuté : entrées, hypothèses, étapes, résultat, version du calculateur. */
    public const string RESULT = 'calculation';

    /** Calcul refusé : les motifs du calculateur, tels quels. */
    public const string REFUSAL = 'calculation_refusal';

    public function __construct(
        private CalculationPresenter $presenter,
        private CalculationTextsInterface $texts,
    ) {
    }

    /**
     * @param string|null $runUuid exécution enregistrée, relisible par GET /api/v1/calculations/{uuid} ;
     *                             null hors de l'application (évaluation)
     */
    public function result(CalculationResult $result, ?string $runUuid, ?\DateTimeImmutable $createdAt, string $locale): Block
    {
        return Block::composed(
            self::RESULT,
            ['calculation' => [
                'uuid' => $runUuid,
                ...$this->presenter->calculation($result, $locale),
                'createdAt' => $createdAt?->format(\DATE_ATOM),
            ]],
            $this->presenter->plainText($result, $locale),
        );
    }

    /**
     * @param list<Refusal> $refusals
     */
    public function refusal(CalculatorDefinition $definition, array $refusals, string $locale): Block
    {
        $title = $this->texts->text($definition->title, [], $locale);
        $reasons = array_map(fn (Refusal $refusal): string => $this->texts->refusal($refusal, $locale), $refusals);

        return Block::composed(
            self::REFUSAL,
            ['calculator' => ['key' => $definition->key, 'title' => $title], 'reasons' => $reasons],
            implode("\n", [
                $this->texts->text('Calcul non effectué : %title%', ['%title%' => $title], $locale),
                ...array_map(static fn (string $reason): string => '- ' . $reason, $reasons),
            ]),
        );
    }
}
