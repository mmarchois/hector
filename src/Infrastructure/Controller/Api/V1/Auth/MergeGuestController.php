<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Auth;

use App\Application\CommandBusInterface;
use App\Application\User\Command\MergeGuestCommand;
use App\Domain\User\Exception\InvalidGuestSessionException;
use App\Infrastructure\Controller\Api\V1\Auth\Dto\MergeGuestRequest;
use App\Infrastructure\Controller\Api\ViolationHttpException;
use App\Infrastructure\Security\AuthenticatedUser;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class MergeGuestController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private AuthenticatedUser $authenticatedUser,
    ) {
    }

    #[Route('/api/v1/auth/merge-guest', name: 'api_auth_merge_guest', methods: ['POST'])]
    #[OA\Tag(name: 'Auth')]
    #[OA\Post(summary: 'Rattacher à mon compte les données d\'un essai sans compte, puis supprimer le compte invité')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: MergeGuestRequest::class)))]
    #[OA\Response(response: 204, description: 'Données rattachées, compte invité supprimé')]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 422, description: 'Session d\'invité absente, expirée ou qui n\'est pas celle d\'un invité')]
    public function __invoke(#[MapRequestPayload] MergeGuestRequest $request): Response
    {
        try {
            $this->commandBus->handle(new MergeGuestCommand(
                $this->authenticatedUser->getUuid(),
                (string) $request->guestRefreshToken,
            ));
        } catch (InvalidGuestSessionException) {
            throw new ViolationHttpException(['guestRefreshToken' => 'Session d\'invité introuvable ou expirée.']);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
