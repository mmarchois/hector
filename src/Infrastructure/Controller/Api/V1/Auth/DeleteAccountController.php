<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Auth;

use App\Application\CommandBusInterface;
use App\Application\User\Command\DeleteUserCommand;
use App\Infrastructure\Security\AuthenticatedUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class DeleteAccountController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private AuthenticatedUser $authenticatedUser,
    ) {
    }

    #[Route('/api/v1/auth/me', name: 'api_auth_me_delete', methods: ['DELETE'])]
    #[OA\Tag(name: 'Auth')]
    #[OA\Delete(summary: 'Suppression du compte (RGPD)')]
    #[OA\Response(response: 204, description: 'Compte supprimé, sessions révoquées')]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    public function __invoke(): Response
    {
        $this->commandBus->handle(new DeleteUserCommand($this->authenticatedUser->getUuid()));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
