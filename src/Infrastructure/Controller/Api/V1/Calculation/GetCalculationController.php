<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Calculation;

use App\Application\Calculation\Query\GetCalculationRunQuery;
use App\Application\QueryBusInterface;
use App\Domain\Calculation\CalculationRun;
use App\Domain\Calculation\Exception\CalculationRunNotFoundException;
use App\Infrastructure\Controller\Api\V1\Calculation\Dto\CalculationResponse;
use App\Infrastructure\Security\AuthenticatedUser;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class GetCalculationController
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private AuthenticatedUser $authenticatedUser,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('/api/v1/calculations/{uuid}', name: 'api_calculations_get', requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[OA\Tag(name: 'Calculs')]
    #[OA\Get(summary: 'Relire un calcul, tel qu\'il a été fait')]
    #[OA\Response(response: 200, description: 'Calcul', content: new OA\JsonContent(ref: new Model(type: CalculationResponse::class)))]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 404, description: 'Calcul inconnu ou d\'un autre utilisateur')]
    public function __invoke(string $uuid): Response
    {
        try {
            /** @var CalculationRun $run */
            $run = $this->queryBus->handle(new GetCalculationRunQuery($uuid, $this->authenticatedUser->getUuid()));
        } catch (CalculationRunNotFoundException) {
            throw new NotFoundHttpException('Calcul introuvable.');
        }

        return new JsonResponse(CalculationResponse::fromRun($run, $this->translator));
    }
}
