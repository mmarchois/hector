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
 * Sans programme, il répond par un brouillon valide : une explication générale, DEFAULT_REPLY.
 */
final class FakeChatModel implements ChatModelInterface
{
    public const string DEFAULT_REPLY = 'Réponse du faux modèle.';

    /** @var list<ChatResponse|ChatModelException> */
    private array $script = [];

    /** @var list<ChatRequest> */
    public array $requests = [];

    /**
     * Brouillon au format de réponse (lot L7). Chaque bloc : [type, kind, text, citations?, source?].
     *
     * @param list<array{0: string, 1: string, 2: string, 3?: list<string>, 4?: string}> $blocks
     */
    public static function draft(array $blocks): string
    {
        return json_encode(['blocks' => array_map(static fn (array $block): array => [
            'type' => $block[0],
            'kind' => $block[1],
            'text' => $block[2],
            'citations' => $block[3] ?? [],
            'source' => $block[4] ?? '',
        ], $blocks)], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }

    /** Brouillon d'un seul énoncé, explication générale par défaut. */
    public static function statement(string $text, string $kind = 'general_explanation'): string
    {
        return self::draft([['statement', $kind, $text]]);
    }

    /** Réponse brute du modèle (texte tel quel, brouillon JSON ou non). */
    public function willReply(string $text, ChatStopReason $stopReason = ChatStopReason::EndTurn, ?ChatUsage $usage = null): self
    {
        $this->script[] = new ChatResponse($text, $stopReason, 'fake-model', $usage ?? new ChatUsage(100, 20));

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
        $next = array_shift($this->script) ?? new ChatResponse(self::statement(self::DEFAULT_REPLY), ChatStopReason::EndTurn, 'fake-model', new ChatUsage(100, 20));

        if ($next instanceof ChatModelException) {
            throw $next;
        }

        return $next;
    }
}
