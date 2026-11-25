<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Conversation\Port\AnswerTexts;
use App\Application\Conversation\Port\AnswerTextsInterface;
use App\Application\Conversation\Service\AnswerEngine;
use App\Domain\Conversation\Block;
use App\Domain\Conversation\StatementKind;

/** Textes de réponse des tests unitaires : le français du code, quelle que soit la langue demandée. */
final class FrenchAnswerTexts implements AnswerTextsInterface
{
    public function for(string $locale): AnswerTexts
    {
        $labels = [];
        foreach (StatementKind::cases() as $kind) {
            $labels[$kind->value] = $kind->label();
        }

        return new AnswerTexts('fr', AnswerEngine::REFUSAL_REPLY, AnswerEngine::EMPTY_REPLY, AnswerEngine::DEGRADED_REPLY, Block::WARNING_LABEL, $labels);
    }
}
