<?php

declare(strict_types=1);

namespace App\Infrastructure\Evaluation;

use App\Application\Conversation\Port\ChatMessage;
use App\Application\Conversation\Port\ChatRole;
use App\Application\Conversation\Port\Participant;

/**
 * Lit les cas d'évaluation : un fichier JSON par famille, dans resources/evaluation/cases.
 * Un cas mal formé arrête le chargement, avec son fichier et son motif : une évaluation ne
 * part pas sur un jeu douteux.
 */
final class CaseLoader
{
    private const array AUDIENCES = [
        'guest' => [true, false],
        'individual' => [false, false],
        'professional' => [false, true],
    ];

    /**
     * @throws InvalidEvaluationCaseException
     *
     * @return list<EvaluationCase>
     */
    public function load(string $directory): array
    {
        $files = glob(rtrim($directory, '/') . '/*.json') ?: [];
        if ($files === []) {
            throw new InvalidEvaluationCaseException(\sprintf('Aucun fichier de cas dans %s.', $directory));
        }
        sort($files);

        $cases = [];
        foreach ($files as $file) {
            $name = basename($file);
            try {
                $data = json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new InvalidEvaluationCaseException(\sprintf('%s : JSON illisible (%s).', $name, $e->getMessage()));
            }

            $family = EvaluationFamily::tryFrom(\is_array($data) && \is_string($data['family'] ?? null) ? $data['family'] : '');
            if ($family === null || !\is_array($data['cases'] ?? null)) {
                throw new InvalidEvaluationCaseException(\sprintf('%s : « family » (%s) et « cases » sont requis.', $name, implode(', ', array_map(static fn (EvaluationFamily $known): string => $known->value, EvaluationFamily::cases()))));
            }

            foreach ($data['cases'] as $item) {
                $case = $this->toCase($name, $family, \is_array($item) ? $item : []);
                if (isset($cases[$case->id])) {
                    throw new InvalidEvaluationCaseException(\sprintf('%s : l\'identifiant « %s » est déjà pris.', $name, $case->id));
                }
                $cases[$case->id] = $case;
            }
        }

        return array_values($cases);
    }

    /** @param array<mixed> $item */
    private function toCase(string $file, EvaluationFamily $family, array $item): EvaluationCase
    {
        $id = \is_string($item['id'] ?? null) ? trim($item['id']) : '';
        $fail = static fn (string $reason): InvalidEvaluationCaseException => new InvalidEvaluationCaseException(
            \sprintf('%s, cas « %s » : %s', $file, $id === '' ? '(sans identifiant)' : $id, $reason),
        );
        if ($id === '') {
            throw $fail('« id » est requis.');
        }

        $audience = \is_string($item['audience'] ?? null) ? $item['audience'] : '';
        if (!isset(self::AUDIENCES[$audience])) {
            throw $fail('« audience » vaut guest, individual ou professional.');
        }

        $locale = \is_string($item['locale'] ?? null) ? $item['locale'] : 'fr';
        if (preg_match('/^[a-z]{2}$/', $locale) !== 1) {
            throw $fail('« locale » est un code de langue à deux lettres (fr par défaut).');
        }

        $question = \is_string($item['question'] ?? null) ? trim($item['question']) : '';
        if ($question === '') {
            throw $fail('« question » est requise.');
        }

        // Historique facultatif : alterné, de l'utilisateur à l'assistant, avant la question
        $messages = [];
        foreach (\is_array($item['history'] ?? null) ? $item['history'] : [] as $index => $entry) {
            $role = ChatRole::tryFrom(\is_array($entry) && \is_string($entry['role'] ?? null) ? $entry['role'] : '');
            $text = \is_array($entry) && \is_string($entry['text'] ?? null) ? trim($entry['text']) : '';
            $expectedRole = $index % 2 === 0 ? ChatRole::User : ChatRole::Assistant;
            if ($role !== $expectedRole || $text === '') {
                throw $fail('« history » alterne des messages « user » et « assistant » non vides, en commençant par « user ».');
            }
            $messages[] = new ChatMessage($role, $text);
        }
        if (\count($messages) % 2 !== 0) {
            throw $fail('« history » se termine par un message « assistant » : la question vient ensuite.');
        }
        $messages[] = new ChatMessage(ChatRole::User, $question);

        $expected = $this->strings($item['expected'] ?? [], $fail, 'expected');
        $forbidden = $this->strings($item['forbidden'] ?? [], $fail, 'forbidden');
        $patterns = $this->strings($item['forbiddenPatterns'] ?? [], $fail, 'forbiddenPatterns');
        foreach ($patterns as $pattern) {
            if (@preg_match($pattern, '') === false) {
                throw $fail(\sprintf('motif interdit invalide : %s', $pattern));
            }
        }

        if ($family->binary() && $forbidden === [] && $patterns === []) {
            throw $fail('une famille à critère binaire demande au moins un interdit (« forbidden » ou « forbiddenPatterns »).');
        }
        if ($family === EvaluationFamily::Technical && $expected === []) {
            throw $fail('une question technique demande au moins un élément attendu (« expected »).');
        }

        $review = \is_array($item['review'] ?? null) ? $item['review'] : [];
        [$guest, $professional] = self::AUDIENCES[$audience];

        return new EvaluationCase(
            id: $id,
            family: $family,
            audience: $audience,
            locale: $locale,
            participant: new Participant($guest, $professional),
            messages: $messages,
            expected: $expected,
            forbidden: $forbidden,
            forbiddenPatterns: $patterns,
            expectedSources: $this->strings($item['expectedSources'] ?? [], $fail, 'expectedSources'),
            reviewedBy: \is_string($review['by'] ?? null) && trim($review['by']) !== '' ? trim($review['by']) : null,
            reviewedAt: \is_string($review['at'] ?? null) && trim($review['at']) !== '' ? trim($review['at']) : null,
            calculation: $this->calculation($item['calculation'] ?? null, $fail),
        );
    }

    /**
     * « calculation » : "none" si aucun résultat de calcul ne doit être montré, ou le calcul
     * attendu, {calculator, value, unit} ; value et unit sont facultatifs.
     *
     * @param \Closure(string): InvalidEvaluationCaseException $fail
     */
    private function calculation(mixed $value, \Closure $fail): ?ExpectedCalculation
    {
        if ($value === null) {
            return null;
        }
        if ($value === 'none') {
            return new ExpectedCalculation(null);
        }

        $calculator = \is_array($value) && \is_string($value['calculator'] ?? null) ? trim($value['calculator']) : '';
        $result = \is_array($value) ? ($value['value'] ?? null) : null;
        $unit = \is_array($value) ? ($value['unit'] ?? null) : null;
        if ($calculator === '' || ($result !== null && !\is_int($result) && !\is_float($result)) || ($unit !== null && !\is_string($unit))) {
            throw $fail('« calculation » vaut "none", ou {calculator, value, unit} avec un calculateur, une valeur numérique et une unité.');
        }

        return new ExpectedCalculation($calculator, $result === null ? null : (float) $result, $unit);
    }

    /**
     * @param \Closure(string): InvalidEvaluationCaseException $fail
     *
     * @return list<string>
     */
    private function strings(mixed $value, \Closure $fail, string $field): array
    {
        if (!\is_array($value)) {
            throw $fail(\sprintf('« %s » est une liste de textes.', $field));
        }

        $strings = [];
        foreach ($value as $entry) {
            if (!\is_string($entry) || trim($entry) === '') {
                throw $fail(\sprintf('« %s » est une liste de textes non vides.', $field));
            }
            $strings[] = trim($entry);
        }

        return $strings;
    }
}
