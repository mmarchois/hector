<?php

declare(strict_types=1);

namespace App\Infrastructure\Evaluation;

use App\Application\Conversation\Port\ChatUsage;

final readonly class JudgeResult
{
    /**
     * @param array<string, array{observed: bool, reason: string}> $verdicts par identifiant de critère ;
     *                                                                       un critère absent n'a pas été jugé
     */
    public function __construct(
        public array $verdicts,
        public ?ChatUsage $usage,
        public ?string $model,
        /** Le juge n'a pas pu répondre : aucun critère n'est jugé */
        public ?string $error,
    ) {
    }
}
