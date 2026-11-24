<?php

declare(strict_types=1);

namespace App\Application\Conversation\Service;

use App\Application\Conversation\Port\ChatMessage;
use App\Application\Conversation\Port\ChatRequest;
use App\Application\Conversation\Port\ChatRole;
use App\Application\Conversation\Port\ParticipantInterface;
use App\Application\Conversation\Port\PromptCatalogInterface;
use App\Domain\Conversation\Block;
use App\Domain\Conversation\Message;
use App\Domain\Conversation\MessageRole;
use App\Domain\Conversation\Repository\MessageRepositoryInterface;
use App\Domain\Conversation\Turn;

/**
 * Assemble ce que reçoit le modèle pour un tour (docs/ARCHITECTURE.md, § 5, étape 2a) :
 * consignes versionnées, profil de l'interlocuteur, historique récent. Aucune donnée
 * d'identité : le modèle reçoit le type de compte, jamais le nom, l'email ou le SIRET.
 */
final readonly class TurnContextBuilder
{
    /** Messages précédents transmis au modèle. */
    public const int HISTORY_LENGTH = 20;

    public function __construct(
        private PromptCatalogInterface $prompts,
        private ParticipantInterface $participants,
        private MessageRepositoryInterface $messageRepository,
    ) {
    }

    public function build(Turn $turn): PreparedTurn
    {
        $conversation = $turn->getConversation();
        $participant = $this->participants->profileOf($conversation->getOwnerUuid());
        // ID3 : un invité a la qualification la plus restrictive, celle d'un particulier
        $audience = $this->prompts->get(!$participant->guest && $participant->professional ? 'audience-professional' : 'audience-individual');
        $system = str_replace('{{audience}}', $audience->text, $this->prompts->get('conversation-system')->text);

        $history = $this->messageRepository->findPage($conversation, $turn->getUserMessage()->getUuid(), self::HISTORY_LENGTH);
        $messages = $this->normalize([...$history, $turn->getUserMessage()]);

        return new PreparedTurn(new ChatRequest($system, $messages), substr(hash('sha256', $system), 0, 12));
    }

    /**
     * Le modèle attend des messages alternés qui commencent par l'utilisateur : on écarte un
     * message de l'assistant en tête de fenêtre, et on fusionne deux messages consécutifs du
     * même auteur (message resté sans réponse après un tour échoué).
     *
     * @param list<Message> $messages
     *
     * @return list<ChatMessage>
     */
    private function normalize(array $messages): array
    {
        $normalized = [];
        foreach ($messages as $message) {
            $role = $message->getRole() === MessageRole::User ? ChatRole::User : ChatRole::Assistant;
            $text = implode("\n\n", array_map(static fn (Block $block): string => $block->fallbackText, $message->getBlocks()));

            if ($normalized === [] && $role === ChatRole::Assistant) {
                continue;
            }

            $last = array_key_last($normalized);
            if ($last !== null && $normalized[$last]->role === $role) {
                $normalized[$last] = new ChatMessage($role, $normalized[$last]->text . "\n\n" . $text);
                continue;
            }

            $normalized[] = new ChatMessage($role, $text);
        }

        return $normalized;
    }
}
