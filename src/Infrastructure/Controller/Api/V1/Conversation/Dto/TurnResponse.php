<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Conversation\Dto;

use App\Domain\Conversation\Turn;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * Tour : le message envoyé et, une fois le tour terminé, la réponse. Le client suit un tour
 * « in_progress » par GET /api/v1/turns/{uuid} (décision D8).
 */
final readonly class TurnResponse implements \JsonSerializable
{
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
    ) {
    }

    public static function fromTurn(Turn $turn): self
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
