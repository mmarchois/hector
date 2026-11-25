<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Calculation;

use App\Application\Calculation\Query\ListCalculatorsQuery;
use App\Application\Calculation\Service\CalculationPresenter;
use App\Application\QueryBusInterface;
use App\Domain\Calculation\CalculatorDefinition;
use App\Infrastructure\Controller\Api\V1\Calculation\Dto\CalculatorResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ListCalculatorsController
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CalculationPresenter $presenter,
    ) {
    }

    #[Route('/api/v1/calculators', name: 'api_calculators_list', methods: ['GET'])]
    #[OA\Tag(name: 'Calculs')]
    #[OA\Get(summary: 'Calculs proposés, avec leurs entrées, leurs hypothèses, leur formule et sa source')]
    #[OA\Response(response: 200, description: 'Calculateurs, dans l\'ordre de présentation', content: new OA\JsonContent(
        properties: [new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: new Model(type: CalculatorResponse::class)))],
        type: 'object',
    ))]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    public function __invoke(): Response
    {
        /** @var list<CalculatorDefinition> $definitions */
        $definitions = $this->queryBus->handle(new ListCalculatorsQuery());

        return new JsonResponse(['items' => array_map(
            fn (CalculatorDefinition $definition): CalculatorResponse => CalculatorResponse::fromDefinition($definition, $this->presenter),
            $definitions,
        )]);
    }
}
