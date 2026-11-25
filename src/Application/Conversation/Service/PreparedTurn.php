<?php

declare(strict_types=1);

namespace App\Application\Conversation\Service;

use App\Application\Conversation\Capability\ToolInterface;
use App\Application\Conversation\Port\ChatRequest;

final readonly class PreparedTurn
{
    public function __construct(
        public ChatRequest $request,
        /** Empreinte des consignes envoyées, pour la trace du tour */
        public string $promptVersion,
        /** Langue de la réponse : celle de l'application de l'utilisateur (décision D19) */
        public string $locale = 'fr',
        /** @var list<ToolInterface> outils des capacités offertes à cet interlocuteur */
        public array $tools = [],
    ) {
    }
}
