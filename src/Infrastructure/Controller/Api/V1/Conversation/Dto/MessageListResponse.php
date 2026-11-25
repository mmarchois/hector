<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Conversation\Dto;

use App\Application\Conversation\Query\MessagePage;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

final readonly class MessageListResponse implements \JsonSerializable
{
    /**
     * @param list<MessageResponse> $items
     */
    public function __construct(
        #[OA\Property(description: 'Du plus ancien au plus récent', type: 'array', items: new OA\Items(ref: new Model(type: MessageResponse::class)))]
        public array $items,
        #[OA\Property(description: 'Des messages plus anciens existent : les demander avec before = uuid du premier message')]
        public bool $hasMore,
    ) {
    }

    public static function fromPage(MessagePage $page): self
    {
        return new self(array_map(MessageResponse::fromMessage(...), $page->items), $page->hasMore);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
