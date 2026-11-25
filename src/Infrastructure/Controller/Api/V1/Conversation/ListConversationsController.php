<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Conversation;

use App\Application\Conversation\Query\ConversationPage;
use App\Application\Conversation\Query\ListConversationsQuery;
use App\Application\QueryBusInterface;
use App\Infrastructure\Controller\Api\V1\Conversation\Dto\ConversationListResponse;
use App\Infrastructure\Security\AuthenticatedUser;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ListConversationsController
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private AuthenticatedUser $authenticatedUser,
    ) {
    }

    #[Route('/api/v1/conversations', name: 'api_conversations_list', methods: ['GET'])]
    #[OA\Tag(name: 'Conversations')]
    #[OA\Get(summary: 'Mes conversations, la plus récemment active d\'abord')]
    #[OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, minimum: 1))]
    #[OA\Response(response: 200, description: 'Page de conversations', content: new OA\JsonContent(ref: new Model(type: ConversationListResponse::class)))]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 422, description: 'Page invalide')]
    public function __invoke(
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] int $page = 1,
    ): Response {
        /** @var ConversationPage $result */
        $result = $this->queryBus->handle(new ListConversationsQuery($this->authenticatedUser->getUuid(), $page));

        return new JsonResponse(ConversationListResponse::fromPage($result));
    }
}
