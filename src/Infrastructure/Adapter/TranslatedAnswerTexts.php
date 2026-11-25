<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Application\Conversation\Port\AnswerTexts;
use App\Application\Conversation\Port\AnswerTextsInterface;
use App\Application\Conversation\Service\AnswerEngine;
use App\Domain\Conversation\Block;
use App\Domain\Conversation\StatementKind;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Textes d'une réponse traduits par le traducteur de Symfony : le texte français du code
 * est la clé (translations/messages.<langue>.yaml).
 */
final readonly class TranslatedAnswerTexts implements AnswerTextsInterface
{
    /**
     * @param list<string> $enabledLocales
     */
    public function __construct(
        private TranslatorInterface $translator,
        #[Autowire('%kernel.enabled_locales%')]
        private array $enabledLocales,
        #[Autowire('%kernel.default_locale%')]
        private string $defaultLocale,
    ) {
    }

    public function for(string $locale): AnswerTexts
    {
        $locale = \in_array($locale, $this->enabledLocales, true) ? $locale : $this->defaultLocale;
        $translate = fn (string $text): string => $this->translator->trans($text, locale: $locale);

        $labels = [];
        foreach (StatementKind::cases() as $kind) {
            $labels[$kind->value] = $translate($kind->label());
        }

        return new AnswerTexts(
            locale: $locale,
            refusalReply: $translate(AnswerEngine::REFUSAL_REPLY),
            emptyReply: $translate(AnswerEngine::EMPTY_REPLY),
            degradedReply: $translate(AnswerEngine::DEGRADED_REPLY),
            warningLabel: $translate(Block::WARNING_LABEL),
            kindLabels: $labels,
            labelFormat: $translate(Block::LABEL_FORMAT),
        );
    }
}
