<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Conversation\Dto;

use App\Domain\Conversation\Block;
use App\Domain\Conversation\Message;
use OpenApi\Attributes as OA;

/** Message d'une conversation (docs/ARCHITECTURE.md, § 10). */
final readonly class MessageResponse implements \JsonSerializable
{
    /**
     * @param list<array<string, mixed>> $blocks
     * @param list<array<string, mixed>> $citations
     */
    public function __construct(
        public string $uuid,
        #[OA\Property(enum: ['user', 'assistant'])]
        public string $role,
        #[OA\Property(
            description: 'Blocs typés et versionnés. Champs communs : type, version, fallbackText (texte à afficher si le type est inconnu de l\'application). Type `text` : champ `text`.',
            type: 'array',
            items: new OA\Items(
                required: ['type', 'version', 'fallbackText'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', example: 'text'),
                    new OA\Property(property: 'version', type: 'integer', example: 1),
                    new OA\Property(property: 'fallbackText', type: 'string'),
                ],
                type: 'object',
                additionalProperties: true,
            ),
        )]
        public array $blocks,
        #[OA\Property(description: 'Sources citées par les blocs (lot L10) ; vide pour l\'instant', type: 'array', items: new OA\Items(type: 'object'))]
        public array $citations,
        #[OA\Property(format: 'date-time')]
        public string $createdAt,
    ) {
    }

    public static function fromMessage(Message $message): self
    {
        return new self(
            uuid: $message->getUuid(),
            role: $message->getRole()->value,
            blocks: array_map(static fn (Block $block): array => $block->toArray(), $message->getBlocks()),
            citations: [],
            createdAt: $message->getCreatedAt()->format(\DATE_ATOM),
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
