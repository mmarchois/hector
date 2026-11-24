<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Conversation;

use App\Application\Conversation\Service\TurnRunner;
use App\Domain\Conversation\Exception\ConversationNotFoundException;
use App\Infrastructure\Controller\Api\V1\Conversation\Dto\SendMessageRequest;
use App\Infrastructure\Controller\Api\V1\Conversation\Dto\TurnResponse;
use App\Infrastructure\Security\AuthenticatedUser;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class SendMessageController
{
    public function __construct(
        private TurnRunner $turnRunner,
        private AuthenticatedUser $authenticatedUser,
    ) {
    }

    #[Route('/api/v1/conversations/{uuid}/messages', name: 'api_conversations_send', requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[OA\Tag(name: 'Conversations')]
    #[OA\Post(summary: 'Envoyer un message : renvoie le tour, terminé ou en cours (suivi par GET /api/v1/turns/{uuid})')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: SendMessageRequest::class)))]
    #[OA\Response(response: 201, description: 'Tour créé ; la réponse est incluse s\'il est terminé', content: new OA\JsonContent(ref: new Model(type: TurnResponse::class)))]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 404, description: 'Conversation inconnue ou d\'un autre utilisateur')]
    #[OA\Response(response: 422, description: 'Message vide ou trop long (violations par champ)')]
    public function __invoke(string $uuid, #[MapRequestPayload] SendMessageRequest $request): Response
    {
        try {
            $turn = $this->turnRunner->send($uuid, $this->authenticatedUser->getUuid(), (string) $request->text);
        } catch (ConversationNotFoundException) {
            throw new NotFoundHttpException('Conversation introuvable.');
        }

        return new JsonResponse(TurnResponse::fromTurn($turn), Response::HTTP_CREATED);
    }
}
