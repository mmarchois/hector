<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Conversation;

use App\Application\CommandBusInterface;
use App\Application\Conversation\Command\CreateConversationCommand;
use App\Domain\Conversation\Conversation;
use App\Infrastructure\Controller\Api\V1\Conversation\Dto\ConversationResponse;
use App\Infrastructure\Security\AuthenticatedUser;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class CreateConversationController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private AuthenticatedUser $authenticatedUser,
    ) {
    }

    #[Route('/api/v1/conversations', name: 'api_conversations_create', methods: ['POST'])]
    #[OA\Tag(name: 'Conversations')]
    #[OA\Post(summary: 'Nouvelle conversation')]
    #[OA\Response(response: 201, description: 'Conversation créée', content: new OA\JsonContent(ref: new Model(type: ConversationResponse::class)))]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    public function __invoke(): Response
    {
        /** @var Conversation $conversation */
        $conversation = $this->commandBus->handle(new CreateConversationCommand($this->authenticatedUser->getUuid()));

        return new JsonResponse(ConversationResponse::fromConversation($conversation), Response::HTTP_CREATED);
    }
}
