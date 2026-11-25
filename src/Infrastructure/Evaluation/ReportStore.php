<?php

declare(strict_types=1);

namespace App\Infrastructure\Evaluation;

/** Rapports d'évaluation sur disque, un fichier JSON daté par exécution. */
final class ReportStore
{
    /** @return string chemin du fichier écrit */
    public function save(EvaluationReport $report, string $directory): string
    {
        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new \RuntimeException(\sprintf('Impossible de créer %s.', $directory));
        }

        $name = \sprintf(
            '%s_%s_%s.json',
            preg_replace('/[^0-9T]/', '-', substr($report->startedAt, 0, 19)),
            preg_replace('/[^a-z0-9.-]/i', '-', $report->model),
            implode('+', $report->promptVersions()) ?: 'sans-consignes',
        );
        $path = rtrim($directory, '/') . '/' . $name;
        file_put_contents($path, json_encode($report->toArray(), \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) . "\n");

        return $path;
    }

    public function load(string $path): EvaluationReport
    {
        if (!is_file($path)) {
            throw new \RuntimeException(\sprintf('Rapport introuvable : %s.', $path));
        }

        return EvaluationReport::fromArray(json_decode((string) file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR));
    }
}
