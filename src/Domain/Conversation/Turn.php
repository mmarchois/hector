<?php

declare(strict_types=1);

namespace App\Domain\Conversation;

use App\Domain\Conversation\Exception\TurnAlreadyFinishedException;

/**
 * Un message de l'utilisateur et la réponse qu'il déclenche (CV3 : exactement un message
 * de l'utilisateur, au plus un message de l'assistant). Le tour existe avant sa réponse :
 * le client suit son statut, que le serveur réponde d'un coup ou non (décision D8).
 */
class Turn
{
    private TurnStatus $status = TurnStatus::InProgress;
    private ?Message $assistantMessage = null;
    private ?\DateTimeImmutable $finishedAt = null;

    public function __construct(
        private string $uuid,
        private Conversation $conversation,
        private Message $userMessage,
        private \DateTimeImmutable $createdAt,
    ) {
        if ($userMessage->getRole() !== MessageRole::User || $userMessage->getConversation() !== $conversation) {
            throw new \InvalidArgumentException('Un tour part d\'un message de l\'utilisateur de la même conversation.');
        }
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getConversation(): Conversation
    {
        return $this->conversation;
    }

    public function getUserMessage(): Message
    {
        return $this->userMessage;
    }

    public function getAssistantMessage(): ?Message
    {
        return $this->assistantMessage;
    }

    public function getStatus(): TurnStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    /** Réponse de l'assistant : terminée, ou dégradée quand elle remplace un brouillon rejeté. */
    public function answer(Message $assistantMessage, \DateTimeImmutable $at, bool $degraded = false): self
    {
        $this->assertInProgress();
        if ($assistantMessage->getRole() !== MessageRole::Assistant || $assistantMessage->getConversation() !== $this->conversation) {
            throw new \InvalidArgumentException('La réponse est un message de l\'assistant de la même conversation.');
        }

        $this->assistantMessage = $assistantMessage;
        $this->status = $degraded ? TurnStatus::Degraded : TurnStatus::Completed;
        $this->finishedAt = $at;

        return $this;
    }

    /** Échec sans réponse (fournisseur indisponible…) : l'utilisateur peut relancer. */
    public function fail(\DateTimeImmutable $at): self
    {
        $this->assertInProgress();
        $this->status = TurnStatus::Failed;
        $this->finishedAt = $at;

        return $this;
    }

    private function assertInProgress(): void
    {
        if ($this->status->isFinished()) {
            throw new TurnAlreadyFinishedException();
        }
    }
}
