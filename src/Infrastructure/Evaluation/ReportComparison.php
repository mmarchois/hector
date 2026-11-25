<?php

declare(strict_types=1);

namespace App\Infrastructure\Evaluation;

/**
 * Compare deux exécutions : ce qui a changé par famille, et les cas dont l'issue diffère.
 * Sert avant tout changement de modèle ou de consignes (docs/SECURITY.md, risque T3).
 */
final class ReportComparison
{
    /**
     * @return array{
     *     families: list<array{label: string, before: string, after: string, coverageBefore: float|null, coverageAfter: float|null}>,
     *     changes: list<array{caseId: string, before: string, after: string}>,
     *     onlyBefore: list<string>,
     *     onlyAfter: list<string>,
     *     regressions: int
     * }
     */
    public static function compare(EvaluationReport $before, EvaluationReport $after): array
    {
        $familiesBefore = $before->families();
        $familiesAfter = $after->families();
        $families = [];
        foreach (EvaluationFamily::cases() as $family) {
            $a = $familiesBefore[$family->value] ?? null;
            $b = $familiesAfter[$family->value] ?? null;
            if ($a === null && $b === null) {
                continue;
            }
            $families[] = [
                'label' => $family->label(),
                'before' => $a === null ? '—' : \sprintf('%d/%d', $a['passed'], $a['cases']),
                'after' => $b === null ? '—' : \sprintf('%d/%d', $b['passed'], $b['cases']),
                'coverageBefore' => $a['coverage'] ?? null,
                'coverageAfter' => $b['coverage'] ?? null,
            ];
        }

        $outcomesBefore = self::outcomes($before);
        $outcomesAfter = self::outcomes($after);
        $changes = [];
        $regressions = 0;
        foreach ($outcomesBefore as $caseId => $outcome) {
            $other = $outcomesAfter[$caseId] ?? null;
            if ($other !== null && $other !== $outcome) {
                $changes[] = ['caseId' => $caseId, 'before' => $outcome, 'after' => $other];
                $regressions += $outcome === 'réussi' ? 1 : 0;
            }
        }

        return [
            'families' => $families,
            'changes' => $changes,
            'onlyBefore' => array_values(array_diff(array_keys($outcomesBefore), array_keys($outcomesAfter))),
            'onlyAfter' => array_values(array_diff(array_keys($outcomesAfter), array_keys($outcomesBefore))),
            'regressions' => $regressions,
        ];
    }

    /** @return array<string, string> issue de chaque cas, par identifiant */
    private static function outcomes(EvaluationReport $report): array
    {
        $outcomes = [];
        foreach ($report->results as $result) {
            $outcomes[$result->caseId] = match ($result->passed()) {
                true => 'réussi',
                false => 'en défaut',
                null => 'non jugé',
            };
        }

        return $outcomes;
    }
}
