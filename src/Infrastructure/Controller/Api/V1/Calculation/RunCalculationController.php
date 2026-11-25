<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Calculation;

use App\Application\Calculation\Command\RunCalculationCommand;
use App\Application\CommandBusInterface;
use App\Domain\Calculation\CalculationRun;
use App\Domain\Calculation\Exception\CalculationRefusedException;
use App\Domain\Calculation\Exception\CalculatorNotFoundException;
use App\Domain\Calculation\Refusal;
use App\Infrastructure\Adapter\CalculationTexts;
use App\Infrastructure\Controller\Api\V1\Calculation\Dto\CalculationResponse;
use App\Infrastructure\Controller\Api\V1\Calculation\Dto\RunCalculationRequest;
use App\Infrastructure\Controller\Api\ViolationHttpException;
use App\Infrastructure\Security\AuthenticatedUser;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class RunCalculationController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private AuthenticatedUser $authenticatedUser,
        #[Autowire(service: 'limiter.calculation')]
        private RateLimiterFactoryInterface $calculationLimiter,
        private CalculationTexts $texts,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('/api/v1/calculations', name: 'api_calculations_run', methods: ['POST'])]
    #[OA\Tag(name: 'Calculs')]
    #[OA\Post(summary: 'Faire un calcul : le résultat est enregistré avec ses entrées, ses étapes et la version du calculateur')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: RunCalculationRequest::class)))]
    #[OA\Response(response: 201, description: 'Calcul fait et enregistré', content: new OA\JsonContent(ref: new Model(type: CalculationResponse::class)))]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 422, description: 'Calcul refusé, avec le motif par champ : `calculator` (inconnu), `inputs.<entrée>` (valeur manquante, sans unité, d\'unité incohérente ou hors bornes) ou `inputs` (résultat hors du domaine couvert). Rien n\'est supposé à la place d\'une valeur absente.')]
    #[OA\Response(response: 429, description: 'Trop de calculs à la suite')]
    public function __invoke(#[MapRequestPayload] RunCalculationRequest $request): Response
    {
        $ownerUuid = $this->authenticatedUser->getUuid();
        if (!$this->calculationLimiter->create($ownerUuid)->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(message: 'Vous lancez trop de calculs à la suite. Patientez un instant.');
        }

        try {
            /** @var CalculationRun $run */
            $run = $this->commandBus->handle(new RunCalculationCommand($ownerUuid, $request->calculator, $request->inputs));
        } catch (CalculatorNotFoundException) {
            throw new ViolationHttpException(['calculator' => 'Calculateur inconnu.']);
        } catch (CalculationRefusedException $e) {
            $violations = [];
            foreach ($e->refusals as $refusal) {
                $violations[$refusal->input === Refusal::RESULT ? 'inputs' : 'inputs.' . $refusal->input] = $this->texts->refusal($refusal);
            }

            throw new ViolationHttpException($violations);
        }

        return new JsonResponse(CalculationResponse::fromRun($run, $this->translator), Response::HTTP_CREATED);
    }
}
