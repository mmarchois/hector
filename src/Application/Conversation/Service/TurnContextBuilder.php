<?php

declare(strict_types=1);

namespace App\Application\Conversation\Service;

use App\Application\Conversation\Capability\CapabilityInterface;
use App\Application\Conversation\Port\ChatMessage;
use App\Application\Conversation\Port\ChatRequest;
use App\Application\Conversation\Port\ChatRole;
use App\Application\Conversation\Port\Participant;
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

    /**
     * Taille maximale de l'historique transmis, en caractères (de l'ordre de 10 000 jetons en
     * français) : borne le coût d'entrée d'un tour, quelle que soit la longueur des réponses
     * précédentes. Les messages les plus anciens sont écartés d'abord ; le message du tour
     * (4 000 caractères au plus) est toujours transmis.
     */
    public const int HISTORY_MAX_CHARACTERS = 40_000;

    /**
     * @param iterable<CapabilityInterface> $capabilities ce que les contextes métier apportent au tour
     */
    public function __construct(
        private PromptCatalogInterface $prompts,
        private ParticipantInterface $participants,
        private MessageRepositoryInterface $messageRepository,
        private iterable $capabilities = [],
    ) {
    }

    public function build(Turn $turn, string $locale = 'fr'): PreparedTurn
    {
        $conversation = $turn->getConversation();
        $history = $this->messageRepository->findPage($conversation, $turn->getUserMessage()->getUuid(), self::HISTORY_LENGTH);

        return $this->compose(
            $this->participants->profileOf($conversation->getOwnerUuid()),
            $this->normalize([...$history, $turn->getUserMessage()]),
            $locale,
        );
    }

    /**
     * Contexte d'un échange donné, sans base de données : c'est par ici que passe
     * l'évaluation (lot L8), avec les mêmes consignes et la même borne que l'application.
     *
     * @param list<ChatMessage> $messages alternés, le premier et le dernier de l'utilisateur
     */
    public function compose(Participant $participant, array $messages, string $locale = 'fr'): PreparedTurn
    {
        // ID3 : un invité a la qualification la plus restrictive, celle d'un particulier
        $audience = $this->prompts->get(!$participant->guest && $participant->professional ? 'audience-professional' : 'audience-individual');
        $system = str_replace('{{audience}}', $audience->text, $this->prompts->get('conversation-system')->text);
        // Hector répond dans la langue de l'application, pas dans celle du message : le contrôle
        // des réponses ne couvre que les langues ouvertes (resources/prompts/language-<langue>.md)
        if (str_contains($system, '{{language}}')) {
            $system = str_replace('{{language}}', trim($this->prompts->get('language-' . $locale)->text), $system);
        }

        // Les capacités : leurs consignes entrent dans celles du tour, leurs outils sont offerts au modèle
        $tools = [];
        $instructions = [];
        foreach ($this->capabilities as $capability) {
            array_push($tools, ...$capability->tools($participant));
            $text = trim($capability->instructions($participant) ?? '');
            if ($text !== '') {
                $instructions[] = $text;
            }
        }
        $system = str_replace('{{capabilities}}', implode("\n\n", $instructions), $system);

        return new PreparedTurn(new ChatRequest($system, $this->trim($messages)), substr(hash('sha256', $system), 0, 12), $locale, $tools);
    }

    /**
     * Écarte les messages les plus anciens au-delà de HISTORY_MAX_CHARACTERS, en gardant une
     * fenêtre qui commence par l'utilisateur et le dernier message dans tous les cas.
     *
     * @param list<ChatMessage> $messages
     *
     * @return list<ChatMessage>
     */
    private function trim(array $messages): array
    {
        $total = array_sum(array_map(static fn (ChatMessage $message): int => mb_strlen($message->text), $messages));
        while (\count($messages) > 1 && ($total > self::HISTORY_MAX_CHARACTERS || $messages[0]->role === ChatRole::Assistant)) {
            $total -= mb_strlen(array_shift($messages)->text);
        }

        return $messages;
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
