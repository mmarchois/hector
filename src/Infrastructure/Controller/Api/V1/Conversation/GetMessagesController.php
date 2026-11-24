<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Conversation;

use App\Application\Conversation\Query\GetMessagesQuery;
use App\Application\Conversation\Query\MessagePage;
use App\Application\QueryBusInterface;
use App\Domain\Conversation\Exception\ConversationNotFoundException;
use App\Infrastructure\Controller\Api\V1\Conversation\Dto\MessageListResponse;
use App\Infrastructure\Security\AuthenticatedUser;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetMessagesController
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private AuthenticatedUser $authenticatedUser,
    ) {
    }

    #[Route('/api/v1/conversations/{uuid}/messages', name: 'api_conversations_messages', requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[OA\Tag(name: 'Conversations')]
    #[OA\Get(summary: 'Messages d\'une conversation, par pages en remontant le temps')]
    #[OA\Parameter(name: 'before', in: 'query', required: false, description: 'Messages antérieurs à ce message ; sans lui, les plus récents', schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 30, maximum: 100, minimum: 1))]
    #[OA\Response(response: 200, description: 'Page de messages', content: new OA\JsonContent(ref: new Model(type: MessageListResponse::class)))]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 404, description: 'Conversation inconnue ou d\'un autre utilisateur')]
    #[OA\Response(response: 422, description: 'Paramètres invalides (violations par champ)')]
    public function __invoke(
        string $uuid,
        #[MapQueryParameter] ?string $before = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] int $limit = GetMessagesQuery::DEFAULT_LIMIT,
    ): Response {
        try {
            /** @var MessagePage $page */
            $page = $this->queryBus->handle(new GetMessagesQuery($uuid, $this->authenticatedUser->getUuid(), $before, $limit));
        } catch (ConversationNotFoundException) {
            throw new NotFoundHttpException('Conversation introuvable.');
        }

        return new JsonResponse(MessageListResponse::fromPage($page));
    }
}
