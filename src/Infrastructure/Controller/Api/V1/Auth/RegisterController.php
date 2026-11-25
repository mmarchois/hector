<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Auth;

use App\Application\CommandBusInterface;
use App\Application\User\Command\RegisterUserCommand;
use App\Domain\User\Exception\EmailAlreadyExistsException;
use App\Domain\User\Exception\TermsOfUseNotAcceptedException;
use App\Domain\User\User;
use App\Infrastructure\Controller\Api\ReasonHttpException;
use App\Infrastructure\Controller\Api\V1\Auth\Dto\ProfileResponse;
use App\Infrastructure\Controller\Api\V1\Auth\Dto\RegisterRequest;
use App\Infrastructure\Controller\Api\ViolationHttpException;
use App\Infrastructure\Security\AuthenticatedUser;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class RegisterController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        #[Autowire(service: 'limiter.registration')]
        private RateLimiterFactoryInterface $registrationLimiter,
        private AuthenticatedUser $authenticatedUser,
    ) {
    }

    #[Route('/api/v1/auth/register', name: 'api_auth_register', methods: ['POST'])]
    #[OA\Tag(name: 'Auth')]
    #[OA\Post(summary: 'Création de compte ; avec le jeton d\'un invité, conversion de ce compte (même uuid, données et session conservées)')]
    #[Security(name: null)]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: RegisterRequest::class)))]
    #[OA\Response(response: 201, description: 'Compte créé ou converti', content: new OA\JsonContent(ref: new Model(type: ProfileResponse::class)))]
    #[OA\Response(response: 409, description: 'Email déjà utilisé, ou conditions d\'utilisation acceptées périmées (reason: terms_of_use_outdated)')]
    #[OA\Response(response: 422, description: 'Données invalides (violations par champ)')]
    #[OA\Response(response: 429, description: 'Trop de tentatives')]
    public function __invoke(
        #[MapRequestPayload] RegisterRequest $request,
        Request $httpRequest,
    ): Response {
        $limiter = $this->registrationLimiter->create($httpRequest->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        try {
            /** @var User $user */
            $user = $this->commandBus->handle(new RegisterUserCommand(
                fullName: (string) $request->fullName,
                email: (string) $request->email,
                password: (string) $request->password,
                professional: $request->professional,
                companyName: $request->companyName,
                siret: $request->siret,
                vatNumber: $request->vatNumber,
                acceptedTermsOfUseVersion: $request->acceptedTermsOfUseVersion,
                // Appelée avec le jeton d'un invité, l'inscription convertit ce compte (ID2)
                currentUserUuid: $this->authenticatedUser->findUuid(),
            ));
        } catch (EmailAlreadyExistsException) {
            throw new ConflictHttpException('Un compte existe déjà avec cet email.');
        } catch (TermsOfUseNotAcceptedException) {
            if ((string) $request->acceptedTermsOfUseVersion === '') {
                throw new ViolationHttpException(['acceptedTermsOfUseVersion' => 'Acceptez les conditions d\'utilisation pour continuer.']);
            }

            throw new ReasonHttpException(
                Response::HTTP_CONFLICT,
                ReasonHttpException::TERMS_OF_USE_OUTDATED,
                'Les conditions d\'utilisation ont changé : relisez-les et acceptez la nouvelle version.',
            );
        }

        return new JsonResponse(ProfileResponse::fromUser($user), Response::HTTP_CREATED);
    }
}
