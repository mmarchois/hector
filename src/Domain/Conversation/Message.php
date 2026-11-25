<?php

declare(strict_types=1);

namespace App\Domain\Conversation;

/**
 * Message d'une conversation. CV2 : un message enregistré ne change plus, d'où l'absence
 * de setter. Agrégat distinct de Conversation : on n'a pas à charger tous les messages
 * pour en ajouter un.
 */
class Message
{
    /** @var list<array<string, mixed>> blocs sérialisés (colonne JSON) */
    private array $blocks;

    /**
     * @param list<Block> $blocks
     */
    public function __construct(
        private string $uuid,
        private Conversation $conversation,
        private MessageRole $role,
        array $blocks,
        private \DateTimeImmutable $createdAt,
    ) {
        if ($blocks === []) {
            throw new \InvalidArgumentException('Un message contient au moins un bloc.');
        }
        $this->blocks = array_map(static fn (Block $block): array => $block->toArray(), $blocks);
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getConversation(): Conversation
    {
        return $this->conversation;
    }

    public function getRole(): MessageRole
    {
        return $this->role;
    }

    /** @return list<Block> */
    public function getBlocks(): array
    {
        return array_map(Block::fromArray(...), $this->blocks);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
