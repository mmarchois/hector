<?php

declare(strict_types=1);

namespace App\Application\Conversation\Service;

use App\Application\Conversation\Port\ChatResponse;
use App\Domain\Conversation\TurnTrace;

/**
 * Accumule la trace d'un tour au fil de ses appels au modèle (CV6) : jetons additionnés,
 * dernier modèle et dernière issue, brouillons rejetés, durée depuis le début du tour.
 */
final class TraceRecorder
{
    private ?string $model = null;
    private ?string $stopReason = null;
    private ?int $inputTokens = null;
    private ?int $outputTokens = null;
    private ?int $cacheReadInputTokens = null;
    private ?int $cacheCreationInputTokens = null;

    /** @var list<array{attempt: int, reasons: list<string>}> */
    private array $rejections = [];

    private readonly int $startedAt;

    public function __construct(
        private readonly string $promptVersion,
    ) {
        $this->startedAt = hrtime(true);
    }

    public function record(ChatResponse $response): void
    {
        $this->model = $response->model;
        $this->stopReason = $response->stopReason->value;
        $this->inputTokens = ($this->inputTokens ?? 0) + $response->usage->inputTokens;
        $this->outputTokens = ($this->outputTokens ?? 0) + $response->usage->outputTokens;
        $this->cacheReadInputTokens = ($this->cacheReadInputTokens ?? 0) + $response->usage->cacheReadInputTokens;
        $this->cacheCreationInputTokens = ($this->cacheCreationInputTokens ?? 0) + $response->usage->cacheCreationInputTokens;
    }

    /** @param list<string> $reasons */
    public function reject(int $attempt, array $reasons): void
    {
        $this->rejections[] = ['attempt' => $attempt, 'reasons' => $reasons];
    }

    public function trace(?string $error = null): TurnTrace
    {
        return new TurnTrace(
            model: $this->model,
            promptVersion: $this->promptVersion,
            stopReason: $this->stopReason,
            inputTokens: $this->inputTokens,
            outputTokens: $this->outputTokens,
            cacheReadInputTokens: $this->cacheReadInputTokens,
            cacheCreationInputTokens: $this->cacheCreationInputTokens,
            durationMs: intdiv(hrtime(true) - $this->startedAt, 1_000_000),
            error: $error,
            rejections: $this->rejections === [] ? null : $this->rejections,
        );
    }
}
