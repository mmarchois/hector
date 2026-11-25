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
     * @param list<string> $evidence     sources citables pendant ce tour : les passages lus
     * @param list<string> $calculations calculs exécutés pendant ce tour, que seul un énoncé
     *                                   « résultat de calcul » peut citer
     *
     * @return list<string>
     */
    public static function check(array $blocks, array $evidence, array $calculations = []): array
    {
        if ($blocks === []) {
            return ['La réponse ne contient aucun bloc.'];
        }

        $reasons = [];
        foreach ($blocks as $index => $block) {
            $position = \sprintf('Bloc %d', $index + 1);
            $text = trim($block->textContent());
            $citations = $block->citations();

            // Le modèle n'écrit que des énoncés, des questions et des mises en garde : un bloc
            // de calcul est composé par le serveur, jamais par lui
            if (!\in_array($block->type, Block::WRITABLE, true)) {
                $reasons[] = \sprintf('%s : type « %s » inconnu.', $position, $block->type);
                continue;
            }
            if ($text === '') {
                $reasons[] = \sprintf('%s : texte vide.', $position);
                continue;
            }

            $unknown = array_values(array_diff($citations, $evidence, $calculations));
            if ($unknown !== []) {
                $reasons[] = \sprintf('%s : cite %s, qui ne fait pas partie des sources lues pendant ce tour.', $position, implode(', ', $unknown));
            }

            $kind = $block->kind();
            if ($block->type === Block::STATEMENT) {
                if ($kind === null) {
                    $reasons[] = \sprintf('%s : un énoncé doit avoir une nature.', $position);
                    continue;
                }
                if ($kind === StatementKind::CalculationResult) {
                    array_push($reasons, ...self::checkCalculationResult($position, $text, $citations, $calculations));
                    continue;
                }

                $cited = array_values(array_intersect($citations, $calculations));
                if ($cited !== []) {
                    $reasons[] = \sprintf('%s : seul un énoncé « %s » cite un calcul (%s).', $position, StatementKind::CalculationResult->label(), implode(', ', $cited));
                }
                // Un calcul n'est pas une source : un fait sourcé s'appuie sur un passage lu
                if ($kind->requiresCitation() && array_diff($citations, $calculations) === []) {
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

    /**
     * Un résultat de calcul n'existe que par le calcul exécuté qu'il cite, et n'en écrit aucun
     * chiffre : c'est le serveur qui montre les entrées, les étapes et le résultat. Ainsi aucun
     * nombre présenté comme un résultat de calcul ne vient du modèle.
     *
     * @param list<string> $citations
     * @param list<string> $calculations
     *
     * @return list<string>
     */
    private static function checkCalculationResult(string $position, string $text, array $citations, array $calculations): array
    {
        $label = StatementKind::CalculationResult->label();
        $reasons = [];

        if (\count($citations) !== 1 || !\in_array($citations[0], $calculations, true)) {
            $reasons[] = \sprintf(
                '%s : un énoncé « %s » cite un calcul exécuté pendant ce tour par sa référence (C1, C2…), et lui seul. Sans calcul exécuté, donne la formule comme explication générale, sans résultat chiffré.',
                $position,
                $label,
            );
        }
        if (preg_match('/\d/', $text) === 1) {
            $reasons[] = \sprintf(
                '%s : un énoncé « %s » ne contient aucun chiffre. L\'application affiche elle-même les entrées, les étapes et le résultat du calcul cité : dis seulement, en quelques mots, ce qui est calculé.',
                $position,
                $label,
            );
        }

        return $reasons;
    }
}
