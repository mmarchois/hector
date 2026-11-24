<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Conversation;

use App\Application\Conversation\Query\GetTurnQuery;
use App\Application\QueryBusInterface;
use App\Domain\Conversation\Exception\TurnNotFoundException;
use App\Domain\Conversation\Turn;
use App\Infrastructure\Controller\Api\V1\Conversation\Dto\TurnResponse;
use App\Infrastructure\Security\AuthenticatedUser;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetTurnController
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private AuthenticatedUser $authenticatedUser,
    ) {
    }

    #[Route('/api/v1/turns/{uuid}', name: 'api_turns_get', requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[OA\Tag(name: 'Conversations')]
    #[OA\Get(summary: 'Suivre un tour')]
    #[OA\Response(response: 200, description: 'Tour', content: new OA\JsonContent(ref: new Model(type: TurnResponse::class)))]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 404, description: 'Tour inconnu ou d\'un autre utilisateur')]
    public function __invoke(string $uuid): Response
    {
        try {
            /** @var Turn $turn */
            $turn = $this->queryBus->handle(new GetTurnQuery($uuid, $this->authenticatedUser->getUuid()));
        } catch (TurnNotFoundException) {
            throw new NotFoundHttpException('Tour introuvable.');
        }

        return new JsonResponse(TurnResponse::fromTurn($turn));
    }
}
