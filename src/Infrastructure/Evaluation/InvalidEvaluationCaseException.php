<?php

declare(strict_types=1);

namespace App\Infrastructure\Evaluation;

/** Cas d'évaluation illisible ou incomplet : le message dit le fichier, le cas et le motif. */
final class InvalidEvaluationCaseException extends \RuntimeException
{
}
