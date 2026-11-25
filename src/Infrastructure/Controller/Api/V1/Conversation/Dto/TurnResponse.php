<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Conversation\Dto;

use App\Domain\Conversation\Turn;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tour : le message envoyé et, une fois le tour terminé, la réponse. Le client suit un tour
 * « in_progress » par GET /api/v1/turns/{uuid} (décision D8).
 */
final readonly class TurnResponse implements \JsonSerializable
{
    /**
     * @param array{reason: string, message: string}|null $failure
     */
    public function __construct(
        public string $uuid,
        public string $conversationUuid,
        #[OA\Property(enum: ['in_progress', 'completed', 'degraded', 'failed'])]
        public string $status,
        #[OA\Property(ref: new Model(type: MessageResponse::class))]
        public MessageResponse $userMessage,
        #[OA\Property(ref: new Model(type: MessageResponse::class), nullable: true)]
        public ?MessageResponse $assistantMessage,
        #[OA\Property(format: 'date-time')]
        public string $createdAt,
        #[OA\Property(format: 'date-time', nullable: true)]
        public ?string $finishedAt,
        #[OA\Property(
            description: 'Tour échoué : motif (unavailable : relancer plus tard ; error : relancer n\'y changera rien) et message à afficher',
            nullable: true,
            properties: [
                new OA\Property(property: 'reason', type: 'string', enum: ['unavailable', 'error']),
                new OA\Property(property: 'message', type: 'string'),
            ],
            type: 'object',
        )]
        public ?array $failure,
    ) {
    }

    /** Message affiché à l'utilisateur quand le tour a échoué (texte français, clé de traduction). */
    private const array FAILURE_MESSAGES = [
        'unavailable' => 'Hector n\'a pas pu répondre : le service est momentanément indisponible. Réessayez dans un instant.',
        'error' => 'Hector n\'a pas pu répondre à ce message à cause d\'une erreur. Si elle se répète, signalez-la.',
    ];

    public static function fromTurn(Turn $turn, TranslatorInterface $translator): self
    {
        $answer = $turn->getAssistantMessage();

        return new self(
            uuid: $turn->getUuid(),
            conversationUuid: $turn->getConversation()->getUuid(),
            status: $turn->getStatus()->value,
            userMessage: MessageResponse::fromMessage($turn->getUserMessage()),
            assistantMessage: $answer !== null ? MessageResponse::fromMessage($answer) : null,
            createdAt: $turn->getCreatedAt()->format(\DATE_ATOM),
            finishedAt: $turn->getFinishedAt()?->format(\DATE_ATOM),
            failure: ($failure = $turn->getFailure()) !== null
                ? ['reason' => $failure->value, 'message' => $translator->trans(self::FAILURE_MESSAGES[$failure->value])]
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
