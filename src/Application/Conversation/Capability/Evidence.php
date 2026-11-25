<?php

declare(strict_types=1);

namespace App\Application\Conversation\Capability;

use App\Domain\Conversation\Block;
use App\Domain\Conversation\Citation;

/**
 * Ce que les outils ont produit pendant un tour, et que la réponse peut donc montrer.
 *
 * Les sources lues (décision D7) : chacune reçoit une référence courte (S1, S2…) ; c'est elle
 * que le modèle écrit dans ses citations, et le contrôle des réponses n'accepte que celles de
 * ce registre.
 *
 * Les pièces et les avis, que le serveur compose lui-même : le modèle n'en écrit rien. Une
 * pièce (un calcul exécuté) se cite par sa référence (C1, C2…) pour dire où la montrer ; un
 * avis (un calcul refusé) est montré d'office, tel quel.
 */
final class Evidence
{
    /** @var array<string, Citation> par référence */
    private array $citations = [];

    /** @var array<string, string> référence de chaque identifiant déjà lu */
    private array $references = [];

    /** @var list<array{reference: string|null, subject: string, block: Block}> pièces (avec référence) et avis (sans), dans l'ordre */
    private array $composed = [];

    private int $exhibits = 0;

    /** Une source relue pendant le même tour garde sa référence. */
    public function register(Citation $citation): string
    {
        if (!isset($this->references[$citation->id])) {
            $reference = 'S' . (\count($this->citations) + 1);
            $this->citations[$reference] = $citation;
            $this->references[$citation->id] = $reference;
        }

        return $this->references[$citation->id];
    }

    /** @return list<string> */
    public function references(): array
    {
        return array_keys($this->citations);
    }

    public function get(string $reference): ?Citation
    {
        return $this->citations[$reference] ?? null;
    }

    /** @return list<Citation> tout ce qui a été lu, dans l'ordre de lecture */
    public function all(): array
    {
        return array_values($this->citations);
    }

    /**
     * Pièce composée par le serveur, à montrer avec la réponse. Elle rend caduc un avis resté
     * sur le même sujet : un calcul qui aboutit efface le refus qui l'a précédé.
     *
     * @param string $subject ce sur quoi porte la pièce (le calculateur), pour la rapprocher d'un avis
     *
     * @return string référence à citer pour dire où la montrer
     */
    public function exhibit(string $subject, Block $block): string
    {
        $this->composed = array_values(array_filter(
            $this->composed,
            static fn (array $item): bool => $item['reference'] !== null || $item['subject'] !== $subject,
        ));
        $reference = 'C' . ++$this->exhibits;
        $this->composed[] = ['reference' => $reference, 'subject' => $subject, 'block' => $block];

        return $reference;
    }

    /** Avis du serveur, montré d'office. Un seul par sujet : le dernier. */
    public function notice(string $subject, Block $block): void
    {
        $this->composed = array_values(array_filter(
            $this->composed,
            static fn (array $item): bool => $item['reference'] !== null || $item['subject'] !== $subject,
        ));
        $this->composed[] = ['reference' => null, 'subject' => $subject, 'block' => $block];
    }

    /** @return list<string> références des pièces */
    public function exhibitReferences(): array
    {
        return array_values(array_filter(array_column($this->composed, 'reference')));
    }

    public function exhibitOf(string $reference): ?Block
    {
        foreach ($this->composed as $item) {
            if ($item['reference'] === $reference) {
                return $item['block'];
            }
        }

        return null;
    }

    /**
     * Ce que la réponse n'a pas placé elle-même et que le serveur montre de son propre chef :
     * les avis, et les pièces qu'aucun énoncé n'a citées.
     *
     * @param list<string> $shown références des pièces déjà montrées
     *
     * @return list<Block>
     */
    public function unshown(array $shown): array
    {
        $blocks = [];
        foreach ($this->composed as $item) {
            if ($item['reference'] === null || !\in_array($item['reference'], $shown, true)) {
                $blocks[] = $item['block'];
            }
        }

        return $blocks;
    }
}
