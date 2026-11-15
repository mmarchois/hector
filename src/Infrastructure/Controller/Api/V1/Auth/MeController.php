<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Auth;

use App\Application\QueryBusInterface;
use App\Application\User\Query\GetUserByUuidQuery;
use App\Domain\User\User;
use App\Infrastructure\Controller\Api\V1\Auth\Dto\ProfileResponse;
use App\Infrastructure\Security\AuthenticatedUser;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class MeController
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private AuthenticatedUser $authenticatedUser,
    ) {
    }

    #[Route('/api/v1/auth/me', name: 'api_auth_me', methods: ['GET'])]
    #[OA\Tag(name: 'Auth')]
    #[OA\Get(summary: 'Profil de l\'utilisateur authentifié')]
    #[OA\Response(response: 200, description: 'Profil', content: new OA\JsonContent(ref: new Model(type: ProfileResponse::class)))]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    public function __invoke(): Response
    {
        /** @var User $user */
        $user = $this->queryBus->handle(new GetUserByUuidQuery($this->authenticatedUser->getUuid()));

        return new JsonResponse(ProfileResponse::fromUser($user));
    }
}
