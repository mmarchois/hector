<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\TermsOfUse;

use App\Application\User\Port\TermsOfUseInterface;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetTermsOfUseController
{
    public function __construct(
        private TermsOfUseInterface $termsOfUse,
    ) {
    }

    #[Route('/api/v1/terms-of-use', name: 'api_terms_of_use', methods: ['GET'])]
    #[OA\Tag(name: 'Conditions d\'utilisation')]
    #[OA\Get(summary: 'Conditions d\'utilisation en vigueur')]
    #[Security(name: null)]
    #[OA\Response(
        response: 200,
        description: 'Version et texte (Markdown) à faire accepter avant le premier message',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'version', type: 'string', example: '2026-11-24'),
            new OA\Property(property: 'content', type: 'string', description: 'Markdown'),
        ]),
    )]
    public function __invoke(): Response
    {
        return new JsonResponse([
            'version' => $this->termsOfUse->currentVersion(),
            'content' => $this->termsOfUse->content(),
        ]);
    }
}
