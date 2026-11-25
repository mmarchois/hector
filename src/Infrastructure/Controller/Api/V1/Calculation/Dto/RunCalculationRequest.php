<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Calculation\Dto;

use OpenApi\Attributes as OA;

/** Validation : sur RunCalculationCommand, puis par le calculateur lui-même. */
final class RunCalculationRequest
{
    #[OA\Property(description: 'Clé d\'un calculateur de GET /api/v1/calculators', example: 'single_phase_current')]
    public ?string $calculator = null;

    /** @var array<mixed>|null */
    #[OA\Property(
        description: 'Une valeur et son unité par entrée du calculateur. Toutes les entrées sont obligatoires : aucune valeur n\'est supposée. Un rapport sans unité (facteur de puissance) se donne sans `unit`.',
        type: 'object',
        example: ['power' => ['value' => 3, 'unit' => 'kW'], 'voltage' => ['value' => 230, 'unit' => 'V'], 'powerFactor' => ['value' => 0.9]],
        additionalProperties: new OA\AdditionalProperties(
            properties: [
                new OA\Property(property: 'value', type: 'number'),
                new OA\Property(property: 'unit', type: 'string'),
            ],
            type: 'object',
        ),
    )]
    public ?array $inputs = null;
}
