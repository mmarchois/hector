<?php

declare(strict_types=1);

namespace App\Application\Conversation\Capability;

use App\Domain\Conversation\Citation;

/**
 * Ce qui a été lu pendant un tour et peut donc être cité (décision D7). Chaque source reçoit
 * une référence courte (S1, S2…) : c'est elle que le modèle écrit dans ses citations, et le
 * contrôle des réponses n'accepte que celles de ce registre.
 */
final class Evidence
{
    /** @var array<string, Citation> par référence */
    private array $citations = [];

    /** @var array<string, string> référence de chaque identifiant déjà lu */
    private array $references = [];

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
}
