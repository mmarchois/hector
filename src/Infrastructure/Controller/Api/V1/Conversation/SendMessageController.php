<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Conversation;

use App\Application\Conversation\Service\TurnRunner;
use App\Domain\Conversation\Exception\ConversationNotFoundException;
use App\Domain\Conversation\Exception\FreeAllowanceExhaustedException;
use App\Domain\Conversation\Exception\ServiceSaturatedException;
use App\Infrastructure\Controller\Api\ReasonHttpException;
use App\Infrastructure\Controller\Api\V1\Conversation\Dto\SendMessageRequest;
use App\Infrastructure\Controller\Api\V1\Conversation\Dto\TurnResponse;
use App\Infrastructure\Security\AuthenticatedUser;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SendMessageController
{
    /** Durée maximale d'un tour exécuté dans la requête : deux appels au modèle et leur marge. */
    private const int TIME_LIMIT_SECONDS = 230;

    public function __construct(
        private TurnRunner $turnRunner,
        private AuthenticatedUser $authenticatedUser,
        #[Autowire(service: 'limiter.conversation_turn')]
        private RateLimiterFactoryInterface $turnLimiter,
        private TranslatorInterface $translator,
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
    #[OA\Response(response: 429, description: 'Trop de messages à la suite, ou allocation gratuite épuisée, en messages ou en jetons (reason: signup_required pour un invité, free_allowance_exhausted sinon)')]
    #[OA\Response(response: 503, description: 'Budget global de jetons des dernières 24 heures atteint (reason: service_saturated)')]
    public function __invoke(string $uuid, #[MapRequestPayload] SendMessageRequest $request, Request $httpRequest): Response
    {
        $ownerUuid = $this->authenticatedUser->getUuid();
        if (!$this->turnLimiter->create($ownerUuid)->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(message: 'Vous envoyez trop de messages à la suite. Patientez un instant.');
        }

        // Le tour se termine et s'enregistre même si l'application se déconnecte entre-temps :
        // elle retrouvera la réponse en relisant la conversation
        ignore_user_abort(true);
        set_time_limit(self::TIME_LIMIT_SECONDS);

        try {
            // Hector répond dans la langue de l'application (Accept-Language, décision D19)
            $turn = $this->turnRunner->send($uuid, $ownerUuid, (string) $request->text, $httpRequest->getLocale());
        } catch (ConversationNotFoundException) {
            throw new NotFoundHttpException('Conversation introuvable.');
        } catch (FreeAllowanceExhaustedException $e) {
            throw $e->signupRequired
                ? new ReasonHttpException(Response::HTTP_TOO_MANY_REQUESTS, ReasonHttpException::SIGNUP_REQUIRED, 'Vous avez atteint la limite de l\'essai sans compte. Créez un compte gratuit pour continuer.')
                : new ReasonHttpException(Response::HTTP_TOO_MANY_REQUESTS, ReasonHttpException::FREE_ALLOWANCE_EXHAUSTED, 'Vous avez atteint la limite d\'utilisation des dernières 24 heures. Réessayez plus tard.');
        } catch (ServiceSaturatedException) {
            throw new ReasonHttpException(Response::HTTP_SERVICE_UNAVAILABLE, ReasonHttpException::SERVICE_SATURATED, 'Hector est très sollicité en ce moment. Réessayez dans quelques heures. En cas de danger immédiat, appelez les secours (18 ou 112).');
        }

        return new JsonResponse(TurnResponse::fromTurn($turn, $this->translator), Response::HTTP_CREATED);
    }
}
