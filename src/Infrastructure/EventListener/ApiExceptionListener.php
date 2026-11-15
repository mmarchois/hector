<?php

declare(strict_types=1);

namespace App\Infrastructure\EventListener;

use App\Infrastructure\Controller\Api\ViolationHttpException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\ValidationFailedException as BusValidationFailedException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException as PayloadValidationFailedException;

/**
 * Toutes les erreurs de l'API sortent en JSON : {code, message} et, pour une
 * erreur de saisie (422), violations: [{property, message}].
 * Les 401 d'authentification sont déjà rendus en JSON par lexik.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
final readonly class ApiExceptionListener
{
    public function __construct(
        #[Autowire('%kernel.debug%')]
        private bool $debug = false,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        $violations = $this->violationsOf($throwable);
        if ($violations !== null) {
            $event->setResponse(new JsonResponse([
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'Validation failed.',
                'violations' => $violations,
            ], Response::HTTP_UNPROCESSABLE_ENTITY));

            return;
        }

        $statusCode = $throwable instanceof HttpExceptionInterface
            ? $throwable->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        // Ne jamais exposer le message des erreurs internes
        $message = $throwable instanceof HttpExceptionInterface && $throwable->getMessage() !== ''
            ? $throwable->getMessage()
            : (Response::$statusTexts[$statusCode] ?? 'Error');

        $payload = [
            'code' => $statusCode,
            'message' => $message,
        ];

        // En dev uniquement : chaîne d'exceptions pour le debug
        if ($this->debug && !$throwable instanceof HttpExceptionInterface) {
            $chain = [];
            for ($e = $throwable; $e !== null; $e = $e->getPrevious()) {
                $chain[] = \sprintf('%s: %s (%s:%d)', $e::class, $e->getMessage(), basename($e->getFile()), $e->getLine());
            }
            $payload['debug'] = $chain;
        }

        $headers = $throwable instanceof HttpExceptionInterface ? $throwable->getHeaders() : [];

        $event->setResponse(new JsonResponse($payload, $statusCode, $headers));
    }

    /**
     * @return list<array{property: string, message: string}>|null null si l'erreur n'est pas une erreur de saisie
     */
    private function violationsOf(\Throwable $throwable): ?array
    {
        // Erreur métier rattachée à un champ par un contrôleur
        if ($throwable instanceof ViolationHttpException) {
            $violations = [];
            foreach ($throwable->violations as $property => $message) {
                $violations[] = ['property' => $property, 'message' => $message];
            }

            return $violations;
        }

        // Validation XML d'une commande (middleware `validation` du bus)
        if ($throwable instanceof BusValidationFailedException) {
            return $this->normalize($throwable->getViolations());
        }

        // Corps de requête mal typé (#[MapRequestPayload])
        $previous = $throwable->getPrevious();
        if ($throwable instanceof HttpExceptionInterface
            && $throwable->getStatusCode() === Response::HTTP_UNPROCESSABLE_ENTITY
            && $previous instanceof PayloadValidationFailedException
        ) {
            return $this->normalize($previous->getViolations());
        }

        return null;
    }

    /**
     * @return list<array{property: string, message: string}>
     */
    private function normalize(ConstraintViolationListInterface $list): array
    {
        $violations = [];
        foreach ($list as $violation) {
            $violations[] = [
                'property' => $violation->getPropertyPath(),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $violations;
    }
}
