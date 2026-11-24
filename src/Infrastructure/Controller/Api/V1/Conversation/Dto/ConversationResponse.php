<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Conversation\Dto;

use App\Domain\Conversation\Conversation;
use OpenApi\Attributes as OA;

final readonly class ConversationResponse implements \JsonSerializable
{
    public function __construct(
        public string $uuid,
        #[OA\Property(description: 'Tiré du premier message ; null tant qu\'aucun message n\'a été envoyé')]
        public ?string $title,
        #[OA\Property(format: 'date-time')]
        public string $createdAt,
        #[OA\Property(format: 'date-time', description: 'Dernière activité')]
        public string $updatedAt,
    ) {
    }

    public static function fromConversation(Conversation $conversation): self
    {
        return new self(
            uuid: $conversation->getUuid(),
            title: $conversation->getTitle(),
            createdAt: $conversation->getCreatedAt()->format(\DATE_ATOM),
            updatedAt: $conversation->getUpdatedAt()->format(\DATE_ATOM),
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
