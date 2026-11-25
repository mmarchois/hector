<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Infrastructure\Evaluation\CaseLoader;
use App\Infrastructure\Evaluation\CaseResult;
use App\Infrastructure\Evaluation\EvaluationCase;
use App\Infrastructure\Evaluation\EvaluationReport;
use App\Infrastructure\Evaluation\Evaluator;
use App\Infrastructure\Evaluation\InvalidEvaluationCaseException;
use App\Infrastructure\Evaluation\Pricing;
use App\Infrastructure\Evaluation\ReportStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * `make eval` : mesure les réponses sur le jeu d'évaluation, avec de vrais appels payants au
 * fournisseur. Jamais lancée par `make test`. Code de sortie 1 si un cas d'une famille à
 * critère binaire (pièges normatifs, demandes dangereuses) n'est pas réussi.
 */
#[AsCommand(name: 'app:eval', description: 'Évalue les réponses d\'Hector sur le jeu de cas (appels réels et payants au modèle)')]
final readonly class EvaluateConsoleCommand
{
    public function __construct(
        private CaseLoader $caseLoader,
        private Evaluator $evaluator,
        private ReportStore $reportStore,
        private Pricing $pricing,
        #[Autowire('%env(CHAT_MODEL)%')]
        private string $model,
        #[Autowire('%env(CHAT_EFFORT)%')]
        private string $effort,
        #[Autowire('%env(EVAL_JUDGE_MODEL)%')]
        private string $judgeModel,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Limiter à une famille : technical, normative_trap, dangerous_request ou calculation')]
        ?string $family = null,
        #[Option(name: 'case', description: 'Limiter à un cas, par son identifiant')]
        ?string $caseId = null,
        #[Option(name: 'no-judge', description: 'Contrôles sans modèle seulement : les critères jugés restent « non jugés »')]
        bool $noJudge = false,
        #[Option(name: 'cases-dir', description: 'Dossier des cas')]
        ?string $casesDir = null,
        #[Option(name: 'output-dir', description: 'Dossier des rapports')]
        ?string $outputDir = null,
    ): int {
        try {
            $cases = $this->caseLoader->load($casesDir ?? $this->projectDir . '/resources/evaluation/cases');
        } catch (InvalidEvaluationCaseException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }
        $cases = array_values(array_filter(
            $cases,
            static fn (EvaluationCase $case): bool => ($family === null || $case->family->value === $family) && ($caseId === null || $case->id === $caseId),
        ));
        if ($cases === []) {
            $io->error('Aucun cas ne correspond à cette sélection.');

            return Command::INVALID;
        }

        $io->title('Évaluation d\'Hector');
        $io->definitionList(
            ['Modèle' => $this->model],
            ['Effort' => $this->effort],
            ['Juge' => $noJudge ? 'désactivé' : $this->judgeModel],
            ['Cas' => (string) \count($cases)],
        );

        $startedAt = new \DateTimeImmutable();
        $results = [];
        foreach ($cases as $case) {
            $result = $this->evaluator->evaluate($case, !$noJudge);
            $results[] = $result;
            $io->writeln(\sprintf(' %s  %s%s', match ($result->passed()) {
                true => '<info>réussi   </info>',
                false => '<error>en défaut</error>',
                null => '<comment>non jugé </comment>',
            }, $case->id, $result->status === CaseResult::DEGRADED ? ' (réponse dégradée)' : ''));

            // Une requête refusée dès le premier cas (clé d'API absente ou invalide) le sera pour tous
            if (\count($results) === 1 && $result->status === CaseResult::FAILED) {
                $io->error(\sprintf('Le fournisseur n\'a pas répondu au premier cas : %s. Vérifiez ANTHROPIC_API_KEY dans .env.local. Évaluation interrompue.', $result->error ?? 'erreur inconnue'));

                return Command::FAILURE;
            }
        }

        $report = new EvaluationReport($startedAt->format(\DATE_ATOM), $this->model, $this->effort, $noJudge ? null : $this->judgeModel, $results);
        $path = $this->reportStore->save($report, $outputDir ?? $this->projectDir . '/var/evaluation');

        $io->section('Résultats par famille');
        $io->table(
            ['Famille', 'Réussis', 'En défaut', 'Non jugés', 'Dégradés', 'Erreurs', 'Éléments attendus', 'Sources retrouvées', 'Cas non relus'],
            array_map(static fn (array $row): array => [
                $row['label'] . ($row['binary'] ? ' (tolérance nulle)' : ''),
                \sprintf('%d/%d', $row['passed'], $row['cases']),
                $row['failed'],
                $row['unjudged'],
                $row['degraded'],
                $row['errors'],
                $row['coverage'] === null ? '—' : \sprintf('%d %%', (int) round($row['coverage'] * 100)),
                $row['retrieval'] === null ? '—' : \sprintf('%d %%', (int) round($row['retrieval'] * 100)),
                $row['unreviewed'],
            ], array_values($report->families())),
        );

        foreach ($results as $result) {
            foreach ($result->checks as $check) {
                if ($check['ok'] === false && $check['kind'] !== CaseResult::EXPECTED) {
                    $io->writeln(\sprintf(' <error>%s</error> %s : %s — %s', $result->caseId, $check['id'], $check['label'], $check['reason']));
                }
            }
        }

        $unreviewed = \count(array_filter($results, static fn (CaseResult $result): bool => !$result->reviewed));
        if ($unreviewed > 0) {
            $io->warning(\sprintf('%d cas sur %d n\'ont pas été relus par un expert : ces résultats ne valent pas décision.', $unreviewed, \count($results)));
        }

        $usage = $report->usage();
        $cost = $report->cost($this->pricing);
        $io->section('Coût de la mesure');
        $io->definitionList(
            ['Version des consignes' => implode(', ', $report->promptVersions()) ?: '—'],
            ['Jetons, réponses' => \sprintf('%d en entrée, %d en sortie, %d lus du cache, %d écrits au cache', $usage['answer']['input'], $usage['answer']['output'], $usage['answer']['cacheRead'], $usage['answer']['cacheCreation'])],
            ['Jetons, juge' => \sprintf('%d en entrée, %d en sortie', $usage['judge']['input'] + $usage['judge']['cacheRead'] + $usage['judge']['cacheCreation'], $usage['judge']['output'])],
            ['Coût' => $cost === null
                ? 'non calculé : un modèle rencontré n\'a pas de prix dans resources/evaluation/pricing.json'
                : \sprintf('%.4f %s (%s)', $cost, $this->pricing->currency, $this->pricing->source)],
            ['Rapport' => $path],
        );

        if (!$report->binaryFamiliesPass()) {
            $io->error('Au moins un cas d\'une famille à tolérance nulle n\'est pas réussi.');

            return Command::FAILURE;
        }
        $io->success('Tous les cas des familles à tolérance nulle sont réussis.');

        return Command::SUCCESS;
    }
}
