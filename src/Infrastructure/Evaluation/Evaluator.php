<?php

declare(strict_types=1);

namespace App\Infrastructure\Evaluation;

use App\Application\Conversation\Port\ChatUsage;
use App\Application\Conversation\Service\AnswerEngine;
use App\Application\Conversation\Service\TurnContextBuilder;
use App\Domain\Conversation\Block;
use App\Domain\Conversation\Citation;
use App\Domain\Conversation\DraftValidator;

/**
 * Passe un cas dans le même moteur que l'application (consignes, modèle, contrôle, reprise),
 * puis vérifie la réponse obtenue : contrôles sans modèle d'abord, juge ensuite.
 */
final readonly class Evaluator
{
    private const array NO_USAGE = ['input' => 0, 'output' => 0, 'cacheRead' => 0, 'cacheCreation' => 0];

    public function __construct(
        private TurnContextBuilder $contextBuilder,
        private AnswerEngine $engine,
        private Judge $judge,
    ) {
    }

    public function evaluate(EvaluationCase $case, bool $withJudge = true): CaseResult
    {
        $prepared = $this->contextBuilder->compose($case->participant, $case->messages, $case->locale);
        $outcome = $this->engine->run($prepared);
        $trace = $outcome->trace;
        $answer = implode("\n", array_map(static fn (Block $block): string => $block->fallbackText, $outcome->blocks));
        $status = match (true) {
            $outcome->failed() => CaseResult::FAILED,
            $outcome->degraded => CaseResult::DEGRADED,
            default => CaseResult::ANSWERED,
        };

        $checks = [];
        $judgeUsage = self::NO_USAGE;
        $judgeModel = null;
        $error = $trace->error;

        if (!$outcome->failed()) {
            // 1. Sans modèle : le contrôle des réponses repassé sur la réponse finale, et les motifs interdits
            $reasons = $outcome->degraded ? [] : DraftValidator::check($outcome->blocks, evidence: []);
            $checks[] = $this->check('D1', CaseResult::DETERMINISTIC, 'La réponse finale passe le contrôle des réponses', $reasons === [], implode(' ', $reasons));
            foreach ($case->forbiddenPatterns as $index => $pattern) {
                $found = preg_match($pattern, $answer, $match) === 1;
                $checks[] = $this->check('P' . ($index + 1), CaseResult::DETERMINISTIC, \sprintf('Motif interdit absent : %s', $pattern), !$found, $found ? \sprintf('Trouvé : « %s »', $match[0]) : '');
            }

            // Sources attendues : la recherche les a-t-elle données à lire ? (mesure de référence du lot L12)
            $read = array_map(static fn (Citation $citation): string => mb_strtolower($citation->title), $outcome->evidence);
            foreach ($case->expectedSources as $index => $title) {
                $found = \in_array(mb_strtolower($title), $read, true);
                $checks[] = $this->check('R' . ($index + 1), CaseResult::RETRIEVAL, \sprintf('Source attendue retrouvée : %s', $title), $found, $found ? '' : 'Aucun passage de cette source n\'a été lu pendant le tour.');
            }

            // 2. Avec le juge : éléments attendus (A…) et comportements interdits (I…)
            $criteria = [];
            foreach ($case->expected as $index => $text) {
                $criteria['A' . ($index + 1)] = $text;
            }
            foreach ($case->forbidden as $index => $text) {
                $criteria['I' . ($index + 1)] = $text;
            }
            $judged = $withJudge ? $this->judge->judge($case, $answer, $criteria) : new JudgeResult([], null, null, 'Juge désactivé.');
            $judgeUsage = $judged->usage === null ? self::NO_USAGE : $this->usage($judged->usage);
            $judgeModel = $judged->model;
            $error ??= $withJudge ? $judged->error : null;

            foreach ($criteria as $id => $text) {
                $verdict = $judged->verdicts[$id] ?? null;
                $forbidden = str_starts_with($id, 'I');
                $checks[] = $this->check(
                    $id,
                    $forbidden ? CaseResult::FORBIDDEN : CaseResult::EXPECTED,
                    $text,
                    // Attendu : présent. Interdit : absent
                    $verdict === null ? null : $verdict['observed'] !== $forbidden,
                    $verdict['reason'] ?? ($judged->error ?? 'Non jugé.'),
                );
            }
        }

        return new CaseResult(
            caseId: $case->id,
            family: $case->family->value,
            audience: $case->audience,
            reviewed: $case->reviewed(),
            status: $status,
            answer: $answer,
            checks: $checks,
            promptVersion: $trace->promptVersion,
            model: $trace->model,
            judgeModel: $judgeModel,
            answerUsage: [
                'input' => $trace->inputTokens ?? 0,
                'output' => $trace->outputTokens ?? 0,
                'cacheRead' => $trace->cacheReadInputTokens ?? 0,
                'cacheCreation' => $trace->cacheCreationInputTokens ?? 0,
            ],
            judgeUsage: $judgeUsage,
            durationMs: $trace->durationMs ?? 0,
            rejections: \count($trace->rejections ?? []),
            error: $error,
        );
    }

    /** @return array{id: string, kind: string, label: string, ok: bool|null, reason: string} */
    private function check(string $id, string $kind, string $label, ?bool $ok, string $reason): array
    {
        return ['id' => $id, 'kind' => $kind, 'label' => $label, 'ok' => $ok, 'reason' => $reason];
    }

    /** @return array{input: int, output: int, cacheRead: int, cacheCreation: int} */
    private function usage(ChatUsage $usage): array
    {
        return [
            'input' => $usage->inputTokens,
            'output' => $usage->outputTokens,
            'cacheRead' => $usage->cacheReadInputTokens,
            'cacheCreation' => $usage->cacheCreationInputTokens,
        ];
    }
}
