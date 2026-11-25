<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Calculation\Dto;

use App\Domain\Calculation\CalculationRun;
use App\Domain\Calculation\CalculationStep;
use App\Domain\Calculation\Rounding;
use OpenApi\Attributes as OA;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Un calcul enregistré, tel qu'il a été fait : entrées, hypothèses, étapes, résultat et version. */
final readonly class CalculationResponse implements \JsonSerializable
{
    /**
     * @param array<string, mixed>       $calculator
     * @param list<string>               $assumptions
     * @param list<array<string, mixed>> $inputs
     * @param list<array<string, mixed>> $steps
     * @param array<string, mixed>       $result
     */
    public function __construct(
        public string $uuid,
        #[OA\Property(
            description: 'Calculateur utilisé, dans sa version d\'alors',
            properties: [
                new OA\Property(property: 'key', type: 'string', example: 'single_phase_current'),
                new OA\Property(property: 'version', type: 'integer', example: 1),
                new OA\Property(property: 'title', type: 'string'),
            ],
            type: 'object',
        )]
        public array $calculator,
        #[OA\Property(example: 'I = P / (U × cos φ)')]
        public string $formula,
        #[OA\Property(description: 'Source de la formule : loi physique, ou référence d\'un texte')]
        public string $source,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string'))]
        public array $assumptions,
        #[OA\Property(
            description: 'Entrées, telles qu\'elles ont été fournies',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'key', type: 'string', example: 'power'),
                    new OA\Property(property: 'label', type: 'string', example: 'Puissance active'),
                    new OA\Property(property: 'symbol', type: 'string', example: 'P'),
                    new OA\Property(property: 'value', type: 'number', example: 3),
                    new OA\Property(property: 'unit', type: 'string', example: 'kW'),
                ],
                type: 'object',
            ),
        )]
        public array $inputs,
        #[OA\Property(
            description: 'Étapes du calcul. `value` est arrondie pour l\'affichage (au plus proche, quatre chiffres significatifs) ; le calcul, lui, se poursuit sur la valeur exacte.',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'label', type: 'string', example: 'Puissance apparente'),
                    new OA\Property(property: 'expression', type: 'string', example: 'S = P / cos φ'),
                    new OA\Property(property: 'value', type: 'number', example: 3333),
                    new OA\Property(property: 'unit', type: 'string', example: 'VA'),
                ],
                type: 'object',
            ),
        )]
        public array $steps,
        #[OA\Property(
            description: 'Résultat. `value` est arrondie selon `roundingRule` ; `exact` est la valeur avant arrondi.',
            properties: [
                new OA\Property(property: 'key', type: 'string', example: 'current'),
                new OA\Property(property: 'label', type: 'string', example: 'Courant absorbé'),
                new OA\Property(property: 'symbol', type: 'string', example: 'I'),
                new OA\Property(property: 'value', type: 'number', example: 14.5),
                new OA\Property(property: 'unit', type: 'string', example: 'A'),
                new OA\Property(property: 'exact', type: 'number', example: 14.492753623188406),
                new OA\Property(property: 'roundingRule', type: 'string'),
            ],
            type: 'object',
        )]
        public array $result,
        #[OA\Property(format: 'date-time')]
        public string $createdAt,
    ) {
    }

    public static function fromRun(CalculationRun $run, TranslatorInterface $translator): self
    {
        $result = $run->getResult();
        $definition = $result->definition;

        $inputs = [];
        foreach ($definition->inputs as $input) {
            $quantity = $result->given[$input->key];
            $inputs[] = [
                'key' => $input->key,
                'label' => $translator->trans($input->label),
                'symbol' => $input->symbol,
                'value' => $quantity->value,
                'unit' => $quantity->unit->value,
            ];
        }

        return new self(
            uuid: $run->getUuid(),
            calculator: ['key' => $definition->key, 'version' => $definition->version, 'title' => $translator->trans($definition->title)],
            formula: $definition->formula,
            source: $translator->trans($definition->source),
            assumptions: array_map(static fn (string $assumption): string => $translator->trans($assumption), $definition->assumptions),
            inputs: $inputs,
            steps: array_map(static fn (CalculationStep $step): array => [
                'label' => $translator->trans($step->label),
                'expression' => $step->expression,
                'value' => Rounding::Nearest->apply($step->value),
                'unit' => $step->unit,
            ], $result->steps),
            result: [
                'key' => $definition->output->key,
                'label' => $translator->trans($definition->output->label),
                'symbol' => $definition->output->symbol,
                'value' => $result->result->value,
                'unit' => $result->result->unit->value,
                'exact' => $result->exact,
                'roundingRule' => $translator->trans($definition->output->rounding->rule()),
            ],
            createdAt: $run->getCreatedAt()->format(\DATE_ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
