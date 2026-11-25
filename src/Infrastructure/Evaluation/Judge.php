<?php

declare(strict_types=1);

namespace App\Infrastructure\Evaluation;

use App\Application\Conversation\Port\ChatMessage;
use App\Application\Conversation\Port\ChatModelException;
use App\Application\Conversation\Port\ChatModelInterface;
use App\Application\Conversation\Port\ChatRequest;
use App\Application\Conversation\Port\ChatRole;
use App\Application\Conversation\Port\PromptCatalogInterface;

/**
 * Juge : un modèle de langage qui dit, critère par critère, si le comportement décrit se
 * trouve dans une réponse. Il ne répond pas à la question et ne note pas : il constate.
 * Ses consignes sont versionnées (resources/prompts/evaluation-judge.md).
 */
final readonly class Judge
{
    public function __construct(
        private ChatModelInterface $judgeModel,
        private PromptCatalogInterface $prompts,
    ) {
    }

    /**
     * @param array<string, string> $criteria critères à juger, par identifiant
     */
    public function judge(EvaluationCase $case, string $answer, array $criteria): JudgeResult
    {
        if ($criteria === []) {
            return new JudgeResult([], null, null, null);
        }

        $exchange = implode("\n", array_map(
            static fn (ChatMessage $message): string => \sprintf('[%s] %s', $message->role === ChatRole::User ? 'Utilisateur' : 'Assistant', $message->text),
            $case->messages,
        ));
        $list = implode("\n", array_map(
            static fn (string $id, string $text): string => \sprintf('%s (%s) : %s', $id, str_starts_with($id, 'I') ? 'interdit' : 'attendu', $text),
            array_keys($criteria),
            $criteria,
        ));

        try {
            $response = $this->judgeModel->complete(new ChatRequest(
                $this->prompts->get('evaluation-judge')->text,
                [new ChatMessage(ChatRole::User, "## Échange\n$exchange\n\n## Réponse à évaluer\n$answer\n\n## Critères\n$list")],
                self::schema(),
            ));
        } catch (ChatModelException $e) {
            return new JudgeResult([], null, null, mb_substr($e->getMessage(), 0, 255));
        }

        try {
            $data = json_decode($response->text, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JudgeResult([], $response->usage, $response->model, 'Le juge n\'a pas répondu au format demandé.');
        }

        $verdicts = [];
        foreach (\is_array($data) && \is_array($data['verdicts'] ?? null) ? $data['verdicts'] : [] as $verdict) {
            $id = \is_array($verdict) && \is_string($verdict['id'] ?? null) ? $verdict['id'] : '';
            // Un verdict sur un critère inconnu, ou sans réponse franche, ne compte pas
            if (isset($criteria[$id]) && \is_bool($verdict['observed'] ?? null)) {
                $verdicts[$id] = [
                    'observed' => $verdict['observed'],
                    'reason' => \is_string($verdict['reason'] ?? null) ? $verdict['reason'] : '',
                ];
            }
        }

        return new JudgeResult($verdicts, $response->usage, $response->model, null);
    }

    /** @return array<string, mixed> */
    private static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'verdicts' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'observed' => ['type' => 'boolean'],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['id', 'observed', 'reason'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['verdicts'],
            'additionalProperties' => false,
        ];
    }
}
