<?php

declare(strict_types=1);

namespace App\Infrastructure\Evaluation;

/**
 * Rapport daté d'une évaluation : ce qui a été mesuré (modèle, effort, version des
 * consignes, juge), les résultats par famille et par cas, et ce que la mesure a coûté.
 */
final readonly class EvaluationReport
{
    /**
     * @param list<CaseResult> $results
     */
    public function __construct(
        public string $startedAt,
        /** Modèle demandé ; celui qui a répondu figure dans chaque résultat (repli du fournisseur) */
        public string $model,
        public string $effort,
        /** Null : évaluation sans juge, seuls les contrôles sans modèle ont tourné */
        public ?string $judgeModel,
        public array $results,
    ) {
    }

    /** @return list<string> versions des consignes rencontrées (une par public) */
    public function promptVersions(): array
    {
        $versions = array_filter(array_map(static fn (CaseResult $result): ?string => $result->promptVersion, $this->results));
        sort($versions);

        return array_values(array_unique($versions));
    }

    /**
     * @return array<string, array{label: string, binary: bool, cases: int, passed: int, failed: int, unjudged: int, degraded: int, errors: int, unreviewed: int, coverage: float|null}>
     */
    public function families(): array
    {
        $summary = [];
        foreach (EvaluationFamily::cases() as $family) {
            $results = array_values(array_filter($this->results, static fn (CaseResult $result): bool => $result->family === $family->value));
            if ($results === []) {
                continue;
            }
            $coverages = array_filter(array_map(static fn (CaseResult $result): ?float => $result->coverage(), $results), static fn (?float $coverage): bool => $coverage !== null);

            $summary[$family->value] = [
                'label' => $family->label(),
                'binary' => $family->binary(),
                'cases' => \count($results),
                'passed' => \count(array_filter($results, static fn (CaseResult $result): bool => $result->passed() === true)),
                'failed' => \count(array_filter($results, static fn (CaseResult $result): bool => $result->passed() === false)),
                'unjudged' => \count(array_filter($results, static fn (CaseResult $result): bool => $result->passed() === null)),
                'degraded' => \count(array_filter($results, static fn (CaseResult $result): bool => $result->status === CaseResult::DEGRADED)),
                'errors' => \count(array_filter($results, static fn (CaseResult $result): bool => $result->status === CaseResult::FAILED)),
                'unreviewed' => \count(array_filter($results, static fn (CaseResult $result): bool => !$result->reviewed)),
                'coverage' => $coverages === [] ? null : array_sum($coverages) / \count($coverages),
            ];
        }

        return $summary;
    }

    /** Vrai si chaque cas d'une famille à critère binaire est réussi : tolérance nulle. */
    public function binaryFamiliesPass(): bool
    {
        foreach ($this->families() as $family) {
            if ($family['binary'] && $family['passed'] !== $family['cases']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{answer: array{input: int, output: int, cacheRead: int, cacheCreation: int}, judge: array{input: int, output: int, cacheRead: int, cacheCreation: int}}
     */
    public function usage(): array
    {
        $total = static function (array $usages): array {
            $sum = ['input' => 0, 'output' => 0, 'cacheRead' => 0, 'cacheCreation' => 0];
            foreach ($usages as $usage) {
                foreach ($sum as $key => $value) {
                    $sum[$key] = $value + $usage[$key];
                }
            }

            return $sum;
        };

        return [
            'answer' => $total(array_map(static fn (CaseResult $result): array => $result->answerUsage, $this->results)),
            'judge' => $total(array_map(static fn (CaseResult $result): array => $result->judgeUsage, $this->results)),
        ];
    }

    /**
     * Coût de la mesure, réponses et juge. Null dès qu'un modèle rencontré n'a pas de prix
     * connu : un total partiel passerait pour le coût réel.
     */
    public function cost(Pricing $pricing): ?float
    {
        $cost = 0.0;
        foreach ($this->results as $result) {
            $answer = $pricing->cost($result->model ?? $this->model, $result->answerUsage);
            $judge = $pricing->cost($result->judgeModel ?? $this->judgeModel, $result->judgeUsage);
            if ($answer === null || $judge === null) {
                return null;
            }
            $cost += $answer + $judge;
        }

        return $cost;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'startedAt' => $this->startedAt,
            'model' => $this->model,
            'effort' => $this->effort,
            'judgeModel' => $this->judgeModel,
            'promptVersions' => $this->promptVersions(),
            'families' => $this->families(),
            'usage' => $this->usage(),
            'results' => array_map(static fn (CaseResult $result): array => $result->toArray(), $this->results),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            startedAt: $data['startedAt'],
            model: $data['model'],
            effort: $data['effort'],
            judgeModel: $data['judgeModel'],
            results: array_map(CaseResult::fromArray(...), $data['results']),
        );
    }
}
