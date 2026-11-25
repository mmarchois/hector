<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Calculation\Dto;

use App\Domain\Calculation\CalculatorDefinition;
use App\Domain\Calculation\InputDefinition;
use App\Domain\Calculation\Unit;
use OpenApi\Attributes as OA;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Ce qu'un calculateur annonce : ses entrées, sa sortie, ses hypothèses, sa formule et sa source. */
final readonly class CalculatorResponse implements \JsonSerializable
{
    /**
     * @param list<array<string, mixed>> $inputs
     * @param array<string, mixed>       $output
     * @param list<string>               $assumptions
     */
    public function __construct(
        #[OA\Property(example: 'single_phase_current')]
        public string $key,
        #[OA\Property(description: 'Elle change avec la formule, une borne ou l\'arrondi ; un calcul enregistré garde la sienne')]
        public int $version,
        public string $title,
        public string $description,
        #[OA\Property(
            description: 'Entrées, toutes obligatoires. `unit` est l\'unité de base, `units` les unités acceptées ; `above` (exclue) et `max` (incluse) bornent la valeur, exprimées dans l\'unité de base.',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'key', type: 'string', example: 'voltage'),
                    new OA\Property(property: 'label', type: 'string', example: 'Tension'),
                    new OA\Property(property: 'symbol', type: 'string', example: 'U'),
                    new OA\Property(property: 'unit', type: 'string', example: 'V'),
                    new OA\Property(property: 'units', type: 'array', items: new OA\Items(type: 'string'), example: ['V', 'mV', 'kV']),
                    new OA\Property(property: 'above', type: 'number', example: 0),
                    new OA\Property(property: 'max', type: 'number', example: 1000),
                ],
                type: 'object',
            ),
        )]
        public array $inputs,
        #[OA\Property(
            description: 'Résultat. `rounding` vaut nearest (au plus proche) ou up (par excès) ; `roundingRule` dit la règle en clair ; au-delà de `max`, le calcul est refusé.',
            properties: [
                new OA\Property(property: 'key', type: 'string', example: 'current'),
                new OA\Property(property: 'label', type: 'string', example: 'Courant absorbé'),
                new OA\Property(property: 'symbol', type: 'string', example: 'I'),
                new OA\Property(property: 'unit', type: 'string', example: 'A'),
                new OA\Property(property: 'rounding', type: 'string', enum: ['nearest', 'up']),
                new OA\Property(property: 'roundingRule', type: 'string'),
                new OA\Property(property: 'max', type: 'number', example: 10000),
            ],
            type: 'object',
        )]
        public array $output,
        #[OA\Property(description: 'Hypothèses du calcul, toutes dites', type: 'array', items: new OA\Items(type: 'string'))]
        public array $assumptions,
        #[OA\Property(example: 'I = P / (U × cos φ)')]
        public string $formula,
        #[OA\Property(description: 'Source de la formule : loi physique, ou référence d\'un texte')]
        public string $source,
    ) {
    }

    public static function fromDefinition(CalculatorDefinition $definition, TranslatorInterface $translator): self
    {
        $output = $definition->output;

        return new self(
            key: $definition->key,
            version: $definition->version,
            title: $translator->trans($definition->title),
            description: $translator->trans($definition->description),
            inputs: array_map(static fn (InputDefinition $input): array => [
                'key' => $input->key,
                'label' => $translator->trans($input->label),
                'symbol' => $input->symbol,
                'unit' => $input->unit->value,
                'units' => array_map(static fn (Unit $unit): string => $unit->value, Unit::of($input->dimension())),
                'above' => $input->above,
                'max' => $input->max,
            ], $definition->inputs),
            output: [
                'key' => $output->key,
                'label' => $translator->trans($output->label),
                'symbol' => $output->symbol,
                'unit' => $output->unit->value,
                'rounding' => $output->rounding->value,
                'roundingRule' => $translator->trans($output->rounding->rule()),
                'max' => $output->max,
            ],
            assumptions: array_map(static fn (string $assumption): string => $translator->trans($assumption), $definition->assumptions),
            formula: $definition->formula,
            source: $translator->trans($definition->source),
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
