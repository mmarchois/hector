<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Api\V1\Conversation\Dto;

use App\Application\Conversation\Query\ConversationPage;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

final readonly class ConversationListResponse implements \JsonSerializable
{
    /**
     * @param list<ConversationResponse> $items
     */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: ConversationResponse::class)))]
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
    ) {
    }

    public static function fromPage(ConversationPage $page): self
    {
        return new self(
            array_map(ConversationResponse::fromConversation(...), $page->items),
            $page->page,
            $page->perPage,
            $page->total,
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
