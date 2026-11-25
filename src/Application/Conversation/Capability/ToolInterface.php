<?php

declare(strict_types=1);

namespace App\Application\Conversation\Capability;

/**
 * Action que le modèle peut demander pendant un tour (docs/ARCHITECTURE.md, § 6). Un outil
 * ne fait que lire, calculer ou proposer une transition que le domaine valide : il ne
 * supprime, n'envoie ni ne publie rien (docs/SECURITY.md, § 7).
 */
interface ToolInterface
{
    public function name(): string;

    /** Quand et comment s'en servir : c'est ce que le modèle lit pour décider de l'appeler. */
    public function description(): string;

    /** @return array<string, mixed> schéma JSON de l'entrée */
    public function inputSchema(): array;

    /**
     * @param array<string, mixed> $input    entrée produite par le modèle : à valider, jamais à croire
     * @param Evidence             $evidence où déclarer ce que l'outil donne à lire, pour que ce soit citable
     */
    public function call(array $input, Evidence $evidence): ToolResult;
}
