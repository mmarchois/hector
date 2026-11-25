<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

use App\Domain\Conversation\StatementKind;

/**
 * Textes d'une réponse dans une langue donnée (décision D19) : les réponses fixes du
 * serveur, et les intitulés qui entrent dans le texte de repli des blocs (CV5).
 */
final readonly class AnswerTexts
{
    /**
     * @param array<string, string> $kindLabels intitulé de chaque nature d'énoncé, par valeur de StatementKind
     */
    public function __construct(
        public string $locale,
        public string $refusalReply,
        public string $emptyReply,
        public string $degradedReply,
        public string $warningLabel,
        public array $kindLabels,
    ) {
    }

    public function label(StatementKind $kind): string
    {
        return $this->kindLabels[$kind->value] ?? $kind->label();
    }
}
