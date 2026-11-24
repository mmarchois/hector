<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Auth;

use App\Application\CommandBusInterface;
use App\Application\User\Command\CreateGuestCommand;
use App\Domain\User\Exception\TermsOfUseNotAcceptedException;
use App\Domain\User\User;
use App\Infrastructure\Controller\Api\ReasonHttpException;
use App\Infrastructure\Controller\Api\V1\Auth\Dto\CreateGuestRequest;
use App\Infrastructure\Security\User\SecurityUser;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/** Essai sans compte (décision D17) : un invité reçoit ses jetons comme après une connexion. */
final readonly class CreateGuestController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        #[Autowire(service: 'lexik_jwt_authentication.handler.authentication_success')]
        private AuthenticationSuccessHandler $authenticationSuccessHandler,
        #[Autowire(service: 'limiter.guest_creation')]
        private RateLimiterFactoryInterface $guestCreationLimiter,
    ) {
    }

    #[Route('/api/v1/auth/guest', name: 'api_auth_guest', methods: ['POST'])]
    #[OA\Tag(name: 'Auth')]
    #[OA\Post(summary: 'Ouverture d\'un compte invité (essai sans compte)')]
    #[Security(name: null)]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: CreateGuestRequest::class)))]
    #[OA\Response(
        response: 201,
        description: 'Compte invité ouvert : mêmes jetons qu\'après une connexion',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'token', type: 'string'),
            new OA\Property(property: 'refresh_token', type: 'string'),
        ]),
    )]
    #[OA\Response(response: 409, description: 'Conditions d\'utilisation acceptées périmées (reason: terms_of_use_outdated)')]
    #[OA\Response(response: 422, description: 'Conditions d\'utilisation non acceptées (violations par champ)')]
    #[OA\Response(response: 429, description: 'Trop de comptes invités ouverts depuis cette adresse')]
    public function __invoke(
        #[MapRequestPayload] CreateGuestRequest $request,
        Request $httpRequest,
    ): Response {
        $limiter = $this->guestCreationLimiter->create($httpRequest->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(message: 'Trop de comptes ouverts depuis cette connexion. Réessayez plus tard ou connectez-vous.');
        }

        try {
            /** @var User $guest */
            $guest = $this->commandBus->handle(new CreateGuestCommand((string) $request->acceptedTermsOfUseVersion));
        } catch (TermsOfUseNotAcceptedException) {
            throw new ReasonHttpException(
                Response::HTTP_CONFLICT,
                ReasonHttpException::TERMS_OF_USE_OUTDATED,
                'Les conditions d\'utilisation ont changé : relisez-les et acceptez la nouvelle version.',
            );
        }

        // Le gestionnaire de lexik signe le JWT ; gesdinet y attache le jeton de rafraîchissement
        $response = $this->authenticationSuccessHandler->handleAuthenticationSuccess(new SecurityUser($guest));
        $response->setStatusCode(Response::HTTP_CREATED);

        return $response;
    }
}
