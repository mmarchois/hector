<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Infrastructure\Evaluation\EvaluationReport;
use App\Infrastructure\Evaluation\Pricing;
use App\Infrastructure\Evaluation\ReportComparison;
use App\Infrastructure\Evaluation\ReportStore;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Compare deux rapports d'évaluation : avant et après un changement de modèle ou de consignes. */
#[AsCommand(name: 'app:eval:compare', description: 'Compare deux rapports d\'évaluation (avant, après)')]
final readonly class CompareEvaluationsConsoleCommand
{
    public function __construct(
        private ReportStore $reportStore,
        private Pricing $pricing,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Rapport de référence (fichier JSON de var/evaluation)')]
        string $before,
        #[Argument(description: 'Rapport à comparer')]
        string $after,
    ): int {
        try {
            $a = $this->reportStore->load($before);
            $b = $this->reportStore->load($after);
        } catch (\RuntimeException|\JsonException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $describe = fn (EvaluationReport $report): string => \sprintf(
            '%s · %s · effort %s · consignes %s · coût %s',
            substr($report->startedAt, 0, 16),
            $report->model,
            $report->effort,
            implode(', ', $report->promptVersions()) ?: '—',
            ($cost = $report->cost($this->pricing)) === null ? 'non calculé' : \sprintf('%.4f %s', $cost, $this->pricing->currency),
        );
        $io->title('Comparaison de deux évaluations');
        $io->definitionList(['Avant' => $describe($a)], ['Après' => $describe($b)]);

        $comparison = ReportComparison::compare($a, $b);
        $percent = static fn (?float $coverage): string => $coverage === null ? '—' : \sprintf('%d %%', (int) round($coverage * 100));
        $io->table(
            ['Famille', 'Réussis avant', 'Réussis après', 'Attendus avant', 'Attendus après'],
            array_map(static fn (array $row): array => [
                $row['label'],
                $row['before'],
                $row['after'],
                $percent($row['coverageBefore']),
                $percent($row['coverageAfter']),
            ], $comparison['families']),
        );

        if ($comparison['changes'] === []) {
            $io->writeln('Aucun cas n\'a changé d\'issue.');
        }
        foreach ($comparison['changes'] as $change) {
            $io->writeln(\sprintf(' %s : %s → %s', $change['caseId'], $change['before'], $change['after']));
        }
        foreach (['onlyBefore' => 'Cas absents du second rapport', 'onlyAfter' => 'Cas absents du premier rapport'] as $key => $label) {
            if ($comparison[$key] !== []) {
                $io->writeln(\sprintf(' %s : %s', $label, implode(', ', $comparison[$key])));
            }
        }

        if ($comparison['regressions'] > 0) {
            $io->error(\sprintf('%d cas réussi(s) avant ne l\'est plus.', $comparison['regressions']));

            return Command::FAILURE;
        }
        $io->success('Aucun cas réussi avant n\'a régressé.');

        return Command::SUCCESS;
    }
}
