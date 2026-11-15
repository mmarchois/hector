<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Auth;

use App\Application\CommandBusInterface;
use App\Application\User\Command\SaveProfileCommand;
use App\Domain\User\Exception\EmailAlreadyExistsException;
use App\Domain\User\Exception\InvalidCurrentPasswordException;
use App\Domain\User\User;
use App\Infrastructure\Controller\Api\V1\Auth\Dto\ProfileResponse;
use App\Infrastructure\Controller\Api\V1\Auth\Dto\UpdateProfileRequest;
use App\Infrastructure\Controller\Api\ViolationHttpException;
use App\Infrastructure\Security\AuthenticatedUser;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class UpdateProfileController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private AuthenticatedUser $authenticatedUser,
    ) {
    }

    #[Route('/api/v1/auth/me', name: 'api_auth_me_update', methods: ['PUT'])]
    #[OA\Tag(name: 'Auth')]
    #[OA\Put(summary: 'Mise à jour du profil (et du mot de passe)')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: UpdateProfileRequest::class)))]
    #[OA\Response(response: 200, description: 'Profil mis à jour', content: new OA\JsonContent(ref: new Model(type: ProfileResponse::class)))]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 409, description: 'Email déjà utilisé')]
    #[OA\Response(response: 422, description: 'Données invalides ou mot de passe actuel incorrect (violations par champ)')]
    public function __invoke(#[MapRequestPayload] UpdateProfileRequest $request): Response
    {
        try {
            /** @var User $user */
            $user = $this->commandBus->handle(new SaveProfileCommand(
                userUuid: $this->authenticatedUser->getUuid(),
                fullName: (string) $request->fullName,
                email: (string) $request->email,
                professional: $request->professional,
                companyName: $request->companyName,
                siret: $request->siret,
                vatNumber: $request->vatNumber,
                currentPassword: $request->currentPassword,
                newPassword: $request->newPassword,
            ));
        } catch (EmailAlreadyExistsException) {
            throw new ConflictHttpException('Un compte existe déjà avec cet email.');
        } catch (InvalidCurrentPasswordException) {
            throw new ViolationHttpException(['currentPassword' => 'Mot de passe actuel incorrect.']);
        }

        return new JsonResponse(ProfileResponse::fromUser($user));
    }
}
