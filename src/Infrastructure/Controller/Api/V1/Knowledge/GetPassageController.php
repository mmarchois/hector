<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Knowledge;

use App\Application\Knowledge\Query\GetPassageQuery;
use App\Application\QueryBusInterface;
use App\Domain\Knowledge\Exception\PassageNotFoundException;
use App\Domain\Knowledge\Passage;
use App\Infrastructure\Controller\Api\V1\Knowledge\Dto\PassageResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetPassageController
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    #[Route('/api/v1/passages/{uuid}', name: 'api_passages_get', requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[OA\Tag(name: 'Documentation')]
    #[OA\Get(summary: 'Consulter un passage cité par une réponse')]
    #[OA\Response(response: 200, description: 'Le passage et sa source ; l\'extrait suit la licence de la source', content: new OA\JsonContent(ref: new Model(type: PassageResponse::class)))]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 404, description: 'Passage inconnu, ou d\'une version qui n\'a pas été publiée')]
    public function __invoke(string $uuid): Response
    {
        try {
            /** @var Passage $passage */
            $passage = $this->queryBus->handle(new GetPassageQuery($uuid));
        } catch (PassageNotFoundException) {
            throw new NotFoundHttpException('Passage introuvable.');
        }

        return new JsonResponse(PassageResponse::fromPassage($passage));
    }
}
