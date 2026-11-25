<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Conversation\Port\ChatModelException;
use App\Application\Conversation\Port\ChatModelInterface;
use App\Application\Conversation\Port\ChatRequest;
use App\Application\Conversation\Port\ChatResponse;
use App\Application\Conversation\Port\ChatStopReason;
use App\Application\Conversation\Port\ChatUsage;

/**
 * Faux modèle de langage scriptable (tests unitaires et d'intégration) : renvoie les
 * réponses ou lève les exceptions programmées, dans l'ordre, et garde les requêtes reçues.
 */
final class FakeChatModel implements ChatModelInterface
{
    public const string DEFAULT_REPLY = 'Réponse du faux modèle.';

    /** @var list<ChatResponse|ChatModelException> */
    private array $script = [];

    /** @var list<ChatRequest> */
    public array $requests = [];

    public function willReply(string $text, ChatStopReason $stopReason = ChatStopReason::EndTurn): self
    {
        $this->script[] = new ChatResponse($text, $stopReason, 'fake-model', new ChatUsage(100, 20));

        return $this;
    }

    public function willThrow(ChatModelException $exception): self
    {
        $this->script[] = $exception;

        return $this;
    }

    public function complete(ChatRequest $request): ChatResponse
    {
        $this->requests[] = $request;
        $next = array_shift($this->script) ?? new ChatResponse(self::DEFAULT_REPLY, ChatStopReason::EndTurn, 'fake-model', new ChatUsage(100, 20));

        if ($next instanceof ChatModelException) {
            throw $next;
        }

        return $next;
    }
}
