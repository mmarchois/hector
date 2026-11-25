<?php

declare(strict_types=1);

namespace App\Application\Conversation\Service;

use App\Application\Conversation\Port\AnswerTexts;
use App\Domain\Conversation\Block;
use App\Domain\Conversation\StatementKind;

/**
 * Format de la réponse demandée au modèle (lot L7) : un objet JSON contraint par un schéma,
 * dont chaque bloc devient un bloc du message (décision D12). Le schéma reste plat (tous les
 * champs requis, « none » et chaînes vides pour l'absence) : c'est la forme la plus sûre pour
 * une sortie structurée, et le contrôle du brouillon fait le reste.
 */
final class AnswerFormat
{
    public const string NO_KIND = 'none';

    /** @return array<string, mixed> */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'blocks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => [Block::STATEMENT, Block::QUESTION, Block::WARNING]],
                            'kind' => ['type' => 'string', 'enum' => [...array_map(static fn (StatementKind $kind): string => $kind->value, StatementKind::cases()), self::NO_KIND]],
                            'text' => ['type' => 'string'],
                            'citations' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'source' => ['type' => 'string'],
                        ],
                        'required' => ['type', 'kind', 'text', 'citations', 'source'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['blocks'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Blocs du brouillon, leurs intitulés de repli dans la langue de $texts. Un champ manquant ou inattendu donne un bloc que le contrôle rejette
     * (type inconnu, nature absente, texte vide), plutôt qu'une exception.
     *
     * @throws \JsonException brouillon qui n'est pas du JSON (réponse tronquée notamment)
     *
     * @return list<Block>
     */
    public static function parse(string $json, ?AnswerTexts $texts = null): array
    {
        $data = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        $items = \is_array($data) && \is_array($data['blocks'] ?? null) ? $data['blocks'] : [];

        $blocks = [];
        foreach ($items as $item) {
            $item = \is_array($item) ? $item : [];
            $type = \is_string($item['type'] ?? null) ? $item['type'] : '';
            $text = \is_string($item['text'] ?? null) ? trim($item['text']) : '';

            $blocks[] = match ($type) {
                Block::QUESTION => Block::question($text),
                Block::WARNING => Block::warning($text, $texts?->warningLabel),
                Block::STATEMENT => self::statement($item, $text, $texts),
                default => Block::fromArray(['type' => $type, 'version' => 1, 'text' => $text, 'fallbackText' => $text]),
            };
        }

        return $blocks;
    }

    /** @param array<mixed> $item */
    private static function statement(array $item, string $text, ?AnswerTexts $texts): Block
    {
        $kind = StatementKind::tryFrom(\is_string($item['kind'] ?? null) ? $item['kind'] : '');
        if ($kind === null) {
            // Nature absente : un énoncé sans nature, que le contrôle rejettera
            return Block::fromArray(['type' => Block::STATEMENT, 'version' => 1, 'text' => $text, 'fallbackText' => $text]);
        }

        $citations = \is_array($item['citations'] ?? null) ? array_values(array_filter($item['citations'], \is_string(...))) : [];
        $source = \is_string($item['source'] ?? null) ? trim($item['source']) : null;

        return Block::statement($kind, $text, $citations, $source, $texts?->label($kind));
    }
}
