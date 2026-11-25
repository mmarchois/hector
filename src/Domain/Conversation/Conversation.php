<?php

declare(strict_types=1);

namespace App\Domain\Conversation;

/**
 * Conversation d'un utilisateur (docs/DOMAIN.md, § 4.1). Le socle ne connaît pas
 * l'utilisateur : il n'en garde que l'uuid (CV1, CV7).
 */
class Conversation
{
    /** Longueur du titre tiré du premier message. */
    public const int TITLE_LENGTH = 80;

    private ?string $title = null;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        private string $uuid,
        private string $ownerUuid,
        private \DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $createdAt;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getOwnerUuid(): string
    {
        return $this->ownerUuid;
    }

    /** CV1 : seul son propriétaire accède à une conversation. */
    public function isOwnedBy(string $userUuid): bool
    {
        return $this->ownerUuid === $userUuid;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** Un message de l'utilisateur : la conversation remonte en tête, son premier message la titre. */
    public function recordUserMessage(string $text, \DateTimeImmutable $at): self
    {
        if ($this->title === null) {
            $title = trim((string) preg_replace('/\s+/u', ' ', $text));
            $this->title = mb_strlen($title) > self::TITLE_LENGTH
                ? rtrim(mb_substr($title, 0, self::TITLE_LENGTH - 1)) . '…'
                : $title;
        }
        $this->updatedAt = $at;

        return $this;
    }

    public function recordActivity(\DateTimeImmutable $at): self
    {
        $this->updatedAt = $at;

        return $this;
    }
}
