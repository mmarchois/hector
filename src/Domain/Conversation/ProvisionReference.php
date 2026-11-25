<?php

declare(strict_types=1);

namespace App\Domain\Conversation;

/**
 * Repère, dans un texte libre, un renvoi à une disposition précise d'un document : article,
 * paragraphe, tableau, section numérotée, annexe, partie, ou numéro de disposition à plusieurs
 * niveaux (771.314.2). Un tel renvoi n'est permis qu'appuyé sur une citation valide : c'est
 * ainsi qu'une référence normative inventée est arrêtée (docs/ROADMAP.md, lot L7).
 *
 * Les mots repérés sont ceux des langues ouvertes (français, anglais et allemand, décision D19) : en
 * ouvrir une autre, c'est ajouter ses mots ici et ses cas dans tests/Critical.
 *
 * La détection repose sur des formulations connues : c'est un filet, pas une garantie
 * (docs/SECURITY.md, § 5). Les cas de référence sont dans tests/Critical.
 */
final class ProvisionReference
{
    /**
     * Quantificateurs possessifs (++, *+) : un numéro n'est jamais tronqué pour échapper à
     * une exclusion (« tableau 13 modules » ne devient pas « tableau 1 »).
     */
    private const array PATTERNS = [
        // article 12, art. 771.314, article R. 4544-3 ; Artikel 12
        '/\bart(?:icle|ikel|\.)\s*+(?:[LRD]\.?\s*+)?\d++(?:[.\-]\d++)*+/iu',
        // § 3, §7.1
        '/§\s*+\d++(?:\.\d++)*+/u',
        // paragraphe 4, chapitre 41, clause 5.2 ; paragraph 4, chapter 41 ; Kapitel 41, Abschnitt 771.4, Absatz 3
        '/\b(?:paragraphe?|chapitre|chapter|clause|kapitel|abschnitt|absatz|abs\.|ziffer)\s++\d++(?:\.\d++)*+/iu',
        // tableau 52H, tableau n° 771.1, mais pas « tableau 13 modules »
        '/\b(?:tableau|tabelle|table|tab\.)\s++(?:n°\s*+)?\d++[A-Z]?+(?:\.\d++)*+(?!\s*+(?:modules?|rang(?:ée)?s?|rows?|ways?|départs?|circuits?|lignes?|colonnes?)\b)/iu',
        // section 771.4, section 5.2.1, mais pas « section 2,5 », « section 2.5 » ni « section 16 » :
        // la section d'un conducteur est trop fréquente pour retenir un numéro sans point, ou
        // un seul chiffre suivi d'une décimale (1.5, 2.5)
        '/\bsection\s++(?:\d{2,}+(?:\.\d++)++|\d(?:\.\d++){2,}+)(?![,.]?\d|\s*+(?:mm|cm|m²))/iu',
        // annexe B, annexe 3 ; annex B, appendix 3
        // « Anlage » n'en fait pas partie : c'est aussi l'installation électrique elle-même
        '/\b(?:annexe?|appendix|anhang)\s++(?:(?-i:[A-Z])|\d++)\b/iu',
        // partie 4, partie 7-701 ; part 7-701 ; Teil 7-701
        // mais pas « zum Teil 30 Jahre alt » (en partie)
        '/(?<!zum )\b(?:partie|part|teil)\s++\d++(?:-\d++)?+\b/iu',
        // numéro de disposition à trois niveaux ou plus : 771.314.2, 52.1.3
        '/(?<![\d.])\d{2,3}(?:\.\d++){2,}+(?![\d.])/u',
    ];

    /** Premier renvoi trouvé dans le texte, ou null. */
    public static function find(string $text): ?string
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                return $match[0];
            }
        }

        return null;
    }
}
