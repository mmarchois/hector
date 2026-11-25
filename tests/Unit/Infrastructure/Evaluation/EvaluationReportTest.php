<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Evaluation;

use App\Infrastructure\Evaluation\CaseResult;
use App\Infrastructure\Evaluation\EvaluationReport;
use App\Infrastructure\Evaluation\Pricing;
use App\Infrastructure\Evaluation\ReportComparison;
use App\Infrastructure\Evaluation\ReportStore;
use PHPUnit\Framework\TestCase;

/** Lot L8 : calcul des résultats, coût, rapport sur disque et comparaison de deux exécutions. */
final class EvaluationReportTest extends TestCase
{
    public function testResultsAreSummedUpByFamily(): void
    {
        $report = $this->report([
            $this->caseResult('t1', 'technical', expected: [true, true]),
            $this->caseResult('t2', 'technical', expected: [true, false], forbidden: [false]),
            $this->caseResult('p1', 'normative_trap', forbidden: [true]),
            $this->caseResult('p2', 'normative_trap', forbidden: [null]),
            $this->caseResult('d1', 'dangerous_request', forbidden: [true], status: CaseResult::DEGRADED, reviewed: true),
            $this->caseResult('d2', 'dangerous_request', status: CaseResult::FAILED),
        ]);

        $families = $report->families();

        self::assertSame(['technical', 'normative_trap', 'dangerous_request'], array_keys($families));
        self::assertSame(['cases' => 2, 'passed' => 1, 'failed' => 1, 'coverage' => 0.75], $this->only($families['technical'], ['cases', 'passed', 'failed', 'coverage']));
        self::assertFalse($families['technical']['binary']);
        self::assertSame(['cases' => 2, 'passed' => 1, 'failed' => 0, 'unjudged' => 1], $this->only($families['normative_trap'], ['cases', 'passed', 'failed', 'unjudged']));
        self::assertSame(['passed' => 1, 'failed' => 1, 'degraded' => 1, 'errors' => 1, 'unreviewed' => 1], $this->only($families['dangerous_request'], ['passed', 'failed', 'degraded', 'errors', 'unreviewed']));
        self::assertNull($families['dangerous_request']['coverage']);
    }

    public function testABinaryFamilyToleratesNoCaseInDefault(): void
    {
        self::assertTrue($this->report([$this->caseResult('t1', 'technical', forbidden: [false]), $this->caseResult('p1', 'normative_trap', forbidden: [true])])->binaryFamiliesPass());
        self::assertFalse($this->report([$this->caseResult('p1', 'normative_trap', forbidden: [true]), $this->caseResult('p2', 'normative_trap', forbidden: [false])])->binaryFamiliesPass());
        // Un cas non jugé n'est pas un cas réussi
        self::assertFalse($this->report([$this->caseResult('d1', 'dangerous_request', forbidden: [null])])->binaryFamiliesPass());
    }

    public function testTheCostAddsAnswersAndJudgeAtEachModelPrice(): void
    {
        $pricing = new Pricing('USD', 'tarif de test', [
            'modele-a' => ['input' => 4.0, 'output' => 20.0, 'cacheRead' => 0.2, 'cacheWrite' => 5.0],
            'juge' => ['input' => 2.0, 'output' => 10.0, 'cacheRead' => 0.1, 'cacheWrite' => 2.5],
        ]);
        $report = $this->report([
            $this->caseResult('t1', 'technical', model: 'modele-a', answer: [1_000_000, 100_000, 500_000, 200_000], judge: [500_000, 10_000, 0, 0]),
            // Identifiant daté renvoyé par le fournisseur : même prix
            $this->caseResult('t2', 'technical', model: 'modele-a-20261001', answer: [1_000_000, 0, 0, 0]),
        ]);

        // t1 : 4 + 2 + 0,1 + 1 = 7,1 ; juge : 1 + 0,1 = 1,1 ; t2 : 4
        self::assertEqualsWithDelta(12.2, $report->cost($pricing), 0.0001);
        self::assertSame(2_000_000, $report->usage()['answer']['input']);
        self::assertSame(500_000, $report->usage()['judge']['input']);
    }

    public function testAnUnknownModelLeavesTheCostUncomputed(): void
    {
        $pricing = new Pricing('USD', 'tarif de test', ['juge' => ['input' => 2.0, 'output' => 10.0, 'cacheRead' => 0.1, 'cacheWrite' => 2.5]]);

        self::assertNull($this->report([$this->caseResult('t1', 'technical', model: 'modele-inconnu', answer: [1000, 100, 0, 0])])->cost($pricing));
    }

    public function testTheShippedPricesLoadWithTheirSource(): void
    {
        $pricing = Pricing::fromFile(\dirname(__DIR__, 4) . '/resources/evaluation/pricing.json');

        self::assertSame('USD', $pricing->currency);
        self::assertNotSame('', $pricing->source);
        self::assertNotNull($pricing->cost('claude-opus-5-5', ['input' => 1000, 'output' => 100, 'cacheRead' => 0, 'cacheCreation' => 0]));
        self::assertNull($pricing->cost('modele-inconnu', ['input' => 1000, 'output' => 100, 'cacheRead' => 0, 'cacheCreation' => 0]));
    }

    public function testAReportIsSavedDatedAndReadBackIdentical(): void
    {
        $directory = sys_get_temp_dir() . '/hector-report-' . bin2hex(random_bytes(6));
        $report = $this->report([$this->caseResult('t1', 'technical', expected: [true], forbidden: [false])]);
        $store = new ReportStore();

        $path = $store->save($report, $directory);

        self::assertSame('2026-11-25T10-00-00_modele-a_abc123def456.json', basename($path));
        $data = json_decode((string) file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['modele-a', 'medium', 'juge', ['abc123def456']], [$data['model'], $data['effort'], $data['judgeModel'], $data['promptVersions']]);
        self::assertFalse($data['results'][0]['passed']);
        self::assertEquals($report, $store->load($path));

        unlink($path);
        rmdir($directory);
    }

    public function testTwoRunsAreComparedCaseByCase(): void
    {
        $before = $this->report([
            $this->caseResult('p1', 'normative_trap', forbidden: [true]),
            $this->caseResult('p2', 'normative_trap', forbidden: [false]),
            $this->caseResult('t1', 'technical', expected: [true, false]),
            $this->caseResult('ancien', 'technical', expected: [true]),
        ]);
        $after = $this->report([
            $this->caseResult('p1', 'normative_trap', forbidden: [false]),
            $this->caseResult('p2', 'normative_trap', forbidden: [true]),
            $this->caseResult('t1', 'technical', expected: [true, true]),
            $this->caseResult('nouveau', 'technical', expected: [true]),
        ]);

        $comparison = ReportComparison::compare($before, $after);

        self::assertSame([
            ['caseId' => 'p1', 'before' => 'réussi', 'after' => 'en défaut'],
            ['caseId' => 'p2', 'before' => 'en défaut', 'after' => 'réussi'],
        ], $comparison['changes']);
        self::assertSame(1, $comparison['regressions']);
        self::assertSame(['ancien'], $comparison['onlyBefore']);
        self::assertSame(['nouveau'], $comparison['onlyAfter']);
        self::assertSame(['label' => 'Questions techniques', 'before' => '2/2', 'after' => '2/2', 'coverageBefore' => 0.75, 'coverageAfter' => 1.0], $comparison['families'][0]);
    }

    /** @param list<CaseResult> $results */
    private function report(array $results): EvaluationReport
    {
        return new EvaluationReport('2026-11-25T10:00:00+00:00', 'modele-a', 'medium', 'juge', $results);
    }

    /**
     * @param list<bool>                $expected  éléments attendus : présents ou non
     * @param list<bool|null>           $forbidden interdits : true si absents (vérification réussie), null si non jugés
     * @param array{int, int, int, int} $answer
     * @param array{int, int, int, int} $judge
     */
    private function caseResult(
        string $id,
        string $family,
        array $expected = [],
        array $forbidden = [],
        string $status = CaseResult::ANSWERED,
        bool $reviewed = false,
        string $model = 'modele-a',
        array $answer = [0, 0, 0, 0],
        array $judge = [0, 0, 0, 0],
    ): CaseResult {
        $checks = [];
        foreach ($expected as $index => $ok) {
            $checks[] = ['id' => 'A' . ($index + 1), 'kind' => CaseResult::EXPECTED, 'label' => 'Attendu', 'ok' => $ok, 'reason' => ''];
        }
        foreach ($forbidden as $index => $ok) {
            $checks[] = ['id' => 'I' . ($index + 1), 'kind' => CaseResult::FORBIDDEN, 'label' => 'Interdit', 'ok' => $ok, 'reason' => ''];
        }
        $usage = static fn (array $tokens): array => ['input' => $tokens[0], 'output' => $tokens[1], 'cacheRead' => $tokens[2], 'cacheCreation' => $tokens[3]];

        return new CaseResult($id, $family, 'individual', $reviewed, $status, 'Réponse.', $checks, 'abc123def456', $model, 'juge', $usage($answer), $usage($judge), 1200, 0, null);
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string>         $keys
     *
     * @return array<string, mixed>
     */
    private function only(array $row, array $keys): array
    {
        return array_intersect_key($row, array_flip($keys));
    }
}
