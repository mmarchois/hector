<?php

declare(strict_types=1);

namespace App\Infrastructure\Evaluation;

/**
 * Résultat d'un cas : la réponse obtenue, chaque vérification avec son issue, et ce que le
 * cas a consommé. Sérialisable : c'est une ligne du rapport.
 */
final readonly class CaseResult
{
    public const string ANSWERED = 'answered';
    public const string DEGRADED = 'degraded';
    public const string FAILED = 'failed';

    public const string DETERMINISTIC = 'deterministic';
    public const string EXPECTED = 'expected';
    public const string FORBIDDEN = 'forbidden';

    /**
     * @param list<array{id: string, kind: string, label: string, ok: bool|null, reason: string}> $checks
     *                                                                                                         ok vaut null quand le juge n'a pas statué
     * @param array{input: int, output: int, cacheRead: int, cacheCreation: int}                  $answerUsage
     * @param array{input: int, output: int, cacheRead: int, cacheCreation: int}                  $judgeUsage
     */
    public function __construct(
        public string $caseId,
        public string $family,
        public string $audience,
        public bool $reviewed,
        /** answered, degraded (réponse fixe du serveur) ou failed (le fournisseur n'a pas répondu) */
        public string $status,
        public string $answer,
        public array $checks,
        public ?string $promptVersion,
        public ?string $model,
        public ?string $judgeModel,
        public array $answerUsage,
        public array $judgeUsage,
        public int $durationMs,
        /** Brouillons rejetés par le contrôle avant la réponse finale */
        public int $rejections,
        public ?string $error,
    ) {
    }

    /**
     * Aucun interdit, aucune vérification en défaut. Null si un interdit n'a pas pu être jugé :
     * le cas n'est ni réussi ni en défaut, il est à rejouer.
     */
    public function passed(): ?bool
    {
        if ($this->status === self::FAILED) {
            return false;
        }

        $unjudged = false;
        foreach ($this->checks as $check) {
            if ($check['kind'] === self::EXPECTED) {
                continue;
            }
            if ($check['ok'] === false) {
                return false;
            }
            $unjudged = $unjudged || $check['ok'] === null;
        }

        return $unjudged ? null : true;
    }

    /** Part des éléments attendus présents dans la réponse ; null sans élément attendu, ou non jugé. */
    public function coverage(): ?float
    {
        $expected = array_values(array_filter($this->checks, static fn (array $check): bool => $check['kind'] === self::EXPECTED));
        if ($expected === [] || $this->status === self::FAILED) {
            return null;
        }
        foreach ($expected as $check) {
            if ($check['ok'] === null) {
                return null;
            }
        }

        return \count(array_filter($expected, static fn (array $check): bool => $check['ok'] === true)) / \count($expected);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [...get_object_vars($this), 'passed' => $this->passed(), 'coverage' => $this->coverage()];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            caseId: $data['caseId'],
            family: $data['family'],
            audience: $data['audience'],
            reviewed: $data['reviewed'],
            status: $data['status'],
            answer: $data['answer'],
            checks: $data['checks'],
            promptVersion: $data['promptVersion'],
            model: $data['model'],
            judgeModel: $data['judgeModel'],
            answerUsage: $data['answerUsage'],
            judgeUsage: $data['judgeUsage'],
            durationMs: $data['durationMs'],
            rejections: $data['rejections'],
            error: $data['error'],
        );
    }
}
