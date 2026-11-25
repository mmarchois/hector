<?php

declare(strict_types=1);

namespace App\Application\Conversation\Port;

/** Textes d'une réponse, dans la langue de l'application de l'utilisateur. */
interface AnswerTextsInterface
{
    /** Une langue qui n'est pas ouverte donne les textes de la langue par défaut. */
    public function for(string $locale): AnswerTexts;
}
