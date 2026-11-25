<?php

declare(strict_types=1);

namespace App\Domain\Conversation;

/**
 * Contrôle déterministe d'un brouillon de réponse (docs/SECURITY.md, § 4, étape 4), avant
 * qu'il ne soit montré à l'utilisateur. Renvoie les motifs de rejet, rédigés pour être
 * renvoyés au modèle lors de la reprise et consignés dans la trace ; aucun motif : accepté.
 *
 * Les lots suivants ajoutent leurs contrôles (registre normatif, gestes permis, consignes
 * interdites) ; celui-ci porte sur la nature des énoncés et sur leurs preuves (CV4).
 */
final class DraftValidator
{
    /**
     * @param list<Block>  $blocks
     * @param list<string> $evidence identifiants citables pendant ce tour : passages lus,
     *                               exécutions de calcul (aucun avant les lots L10 et L14)
     *
     * @return list<string>
     */
    public static function check(array $blocks, array $evidence): array
    {
        if ($blocks === []) {
            return ['La réponse ne contient aucun bloc.'];
        }

        $reasons = [];
        foreach ($blocks as $index => $block) {
            $position = \sprintf('Bloc %d', $index + 1);
            $text = trim($block->textContent());
            $citations = $block->citations();

            if (!\in_array($block->type, [Block::STATEMENT, Block::QUESTION, Block::WARNING], true)) {
                $reasons[] = \sprintf('%s : type « %s » inconnu.', $position, $block->type);
                continue;
            }
            if ($text === '') {
                $reasons[] = \sprintf('%s : texte vide.', $position);
                continue;
            }

            $unknown = array_values(array_diff($citations, $evidence));
            if ($unknown !== []) {
                $reasons[] = \sprintf('%s : cite %s, qui ne fait pas partie des sources lues pendant ce tour.', $position, implode(', ', $unknown));
            }

            $kind = $block->kind();
            if ($block->type === Block::STATEMENT) {
                if ($kind === null) {
                    $reasons[] = \sprintf('%s : un énoncé doit avoir une nature.', $position);
                    continue;
                }
                if ($kind->requiresCitation() && $citations === []) {
                    $reasons[] = \sprintf(
                        '%s : un énoncé « %s » doit citer une source lue pendant ce tour. Sans source, présente-le comme explication générale, hypothèse ou recommandation.',
                        $position,
                        $kind->label(),
                    );
                }
                if ($kind === StatementKind::ReportedOpinion && trim($block->source() ?? '') === '') {
                    $reasons[] = \sprintf('%s : un avis rapporté doit nommer sa source.', $position);
                }
            }

            // Un renvoi précis n'est permis que s'il est appuyé sur une citation valide
            $reference = ProvisionReference::find($text);
            if ($reference !== null && ($citations === [] || $unknown !== [])) {
                $reasons[] = \sprintf(
                    '%s : renvoie à une disposition précise (« %s ») sans citer de source lue pendant ce tour. Retire ce renvoi ou décris le principe sans numéro.',
                    $position,
                    $reference,
                );
            }
        }

        return $reasons;
    }
}
