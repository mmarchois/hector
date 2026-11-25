<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Conversation\Dto;

use App\Domain\Conversation\Block;
use App\Domain\Conversation\Citation;
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
            description: 'Blocs typés et versionnés. Champs communs : type, version, fallbackText (texte à afficher si le type ou la nature est inconnu de l\'application). Type `text` : champ `text`. Type `statement` (énoncé de l\'assistant) : `kind` (sourced_fact, declared_fact, calculation_result, general_explanation, hypothesis, recommendation, missing_information, reported_opinion), `text`, `citations` (identifiants), `source` pour un avis rapporté. Types `question` et `warning` : champ `text`.',
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
        #[OA\Property(description: 'Sources citées par les blocs, composées par le serveur : id (passage, consultable par GET /api/v1/passages/{id}), corpus, title, publisher, version, publishedOn, effectiveFrom, effectiveUntil, page, section, article, excerpt (null : simple renvoi). Un bloc y renvoie par `citations`, liste d\'identifiants', type: 'array', items: new OA\Items(type: 'object'))]
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
            citations: array_map(static fn (Citation $citation): array => $citation->toArray(), $message->getCitations()),
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
