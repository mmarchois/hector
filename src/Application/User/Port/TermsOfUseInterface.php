<?php

declare(strict_types=1);

namespace App\Application\User\Port;

/** Conditions d'utilisation en vigueur, acceptées avant le premier message (docs/SECURITY.md, § 8). */
interface TermsOfUseInterface
{
    public function currentVersion(): string;

    /** Texte en Markdown, affiché tel quel par l'application mobile. */
    public function content(): string;
}
