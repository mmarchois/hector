<?php

declare(strict_types=1);

namespace App\Application\Conversation\Service;

use App\Application\Conversation\Capability\Evidence;
use App\Application\Conversation\Capability\ToolInterface;
use App\Application\Conversation\Port\AnswerTexts;
use App\Application\Conversation\Port\AnswerTextsInterface;
use App\Application\Conversation\Port\ChatMessage;
use App\Application\Conversation\Port\ChatModelException;
use App\Application\Conversation\Port\ChatModelInterface;
use App\Application\Conversation\Port\ChatModelUnavailableException;
use App\Application\Conversation\Port\ChatRequest;
use App\Application\Conversation\Port\ChatResponse;
use App\Application\Conversation\Port\ChatRole;
use App\Application\Conversation\Port\ChatStopReason;
use App\Application\Conversation\Port\ChatTool;
use App\Application\Conversation\Port\ChatToolCall;
use App\Application\Conversation\Port\ChatToolResult;
use App\Domain\Conversation\Block;
use App\Domain\Conversation\Citation;
use App\Domain\Conversation\DraftValidator;
use App\Domain\Conversation\StatementKind;
use App\Domain\Conversation\TurnFailure;

/**
 * Cœur d'un tour, sans base de données (docs/ARCHITECTURE.md, § 5, étape 2) : le modèle
 * répond ou appelle des outils, dans une boucle bornée ; son brouillon passe le contrôle
 * déterministe ; un brouillon rejeté donne lieu à une seule reprise avec les motifs, puis à
 * une réponse dégradée qui le dit. Un brouillon rejeté n'est jamais renvoyé
 * (docs/SECURITY.md, § 4). L'application (TurnRunner) et l'évaluation (lot L8) passent
 * toutes deux par ici : ce qu'on mesure est ce que l'utilisateur reçoit.
 */
final readonly class AnswerEngine
{
    /** Réponse fixe quand le fournisseur refuse de répondre (texte français, traduit par AnswerTextsInterface). */
    public const string REFUSAL_REPLY = 'Je ne peux pas répondre à cette demande. Pour toute intervention sur une installation électrique, faites appel à un électricien qualifié.';

    /** Réponse fixe quand le modèle ne renvoie aucun texte. */
    public const string EMPTY_REPLY = 'Je n\'ai pas pu formuler de réponse. Pouvez-vous reformuler votre question ?';

    /** Réponse fixe quand le brouillon et sa reprise sont rejetés par le contrôle, ou quand la boucle d'outils atteint sa borne. */
    public const string DEGRADED_REPLY = 'Je n\'ai pas pu formuler une réponse assez fiable à cette question. Pouvez-vous la préciser ou la reformuler ? Pour une intervention sur une installation, faites appel à un électricien qualifié.';

    /** Brouillons pour un tour : le premier et une reprise. */
    public const int MAX_ATTEMPTS = 2;

    /** Échanges d'outils pour un tour : au-delà, le tour se termine en réponse dégradée, pas en erreur muette. */
    public const int MAX_TOOL_ROUNDS = 4;

    public function __construct(
        private ChatModelInterface $chatModel,
        private AnswerTextsInterface $answerTexts,
    ) {
    }

    public function run(PreparedTurn $prepared): TurnOutcome
    {
        $trace = new TraceRecorder($prepared->promptVersion);
        $texts = $this->answerTexts->for($prepared->locale);
        $evidence = new Evidence();

        /** @var array<string, ToolInterface> $tools */
        $tools = [];
        foreach ($prepared->tools as $tool) {
            $tools[$tool->name()] = $tool;
        }
        $chatTools = array_map(
            static fn (ToolInterface $tool): ChatTool => new ChatTool($tool->name(), $tool->description(), $tool->inputSchema()),
            array_values($tools),
        );

        $messages = $prepared->request->messages;
        $attempt = 0;
        $toolRounds = 0;

        while (true) {
            try {
                $response = $this->chatModel->complete(new ChatRequest($prepared->request->system, $messages, AnswerFormat::schema(), $chatTools));
            } catch (ChatModelException $e) {
                return new TurnOutcome(
                    [],
                    degraded: false,
                    failure: $e instanceof ChatModelUnavailableException ? TurnFailure::Unavailable : TurnFailure::Error,
                    trace: $trace->trace(mb_substr($e->getMessage(), 0, 255)),
                    evidence: $evidence->all(),
                );
            }
            $trace->record($response);

            if ($response->stopReason === ChatStopReason::Refusal) {
                return $this->fixed($texts->refusalReply, $trace, $evidence);
            }

            // Le modèle demande des outils : on les exécute et on lui rend leurs résultats
            if ($response->toolCalls !== []) {
                if (++$toolRounds > self::MAX_TOOL_ROUNDS) {
                    return $this->fixed($texts->degradedReply, $trace, $evidence);
                }
                $messages[] = new ChatMessage(ChatRole::Assistant, $response->text, toolCalls: $response->toolCalls, providerContent: $response->providerContent);
                $messages[] = new ChatMessage(ChatRole::User, '', toolResults: array_map(
                    fn (ChatToolCall $call): ChatToolResult => $this->call($tools[$call->name] ?? null, $call, $evidence, $trace),
                    $response->toolCalls,
                ));

                continue;
            }

            if (trim($response->text) === '') {
                return $this->fixed($texts->emptyReply, $trace, $evidence);
            }

            [$blocks, $reasons] = $this->check($response, $texts, $evidence);
            if ($reasons === []) {
                [$blocks, $citations] = $this->resolve($blocks, $evidence);

                return new TurnOutcome($blocks, degraded: false, failure: null, trace: $trace->trace(), citations: $citations, evidence: $evidence->all());
            }
            $trace->reject(++$attempt, $reasons);
            if ($attempt >= self::MAX_ATTEMPTS) {
                return $this->fixed($texts->degradedReply, $trace, $evidence);
            }

            // Reprise : le brouillon rejeté et ses motifs, à la suite de l'échange
            $messages[] = new ChatMessage(ChatRole::Assistant, $response->text, providerContent: $response->providerContent);
            $messages[] = new ChatMessage(ChatRole::User, $this->retryInstruction($reasons));
        }
    }

    /** Un outil inconnu ou en échec rend une erreur au modèle : le tour continue sans lui. */
    private function call(?ToolInterface $tool, ChatToolCall $call, Evidence $evidence, TraceRecorder $trace): ChatToolResult
    {
        $before = \count($evidence->all());
        if ($tool === null) {
            $trace->tool($call->name, $call->input, [], true);

            return new ChatToolResult($call->id, \sprintf('Outil inconnu : %s.', $call->name), true);
        }

        try {
            $result = $tool->call($call->input, $evidence);
            $content = $result->content;
            $error = $result->error;
        } catch (\Throwable) {
            $content = 'L\'outil a échoué. Réponds sans lui, en le disant.';
            $error = true;
        }
        $trace->tool(
            $call->name,
            $call->input,
            array_map(static fn (Citation $citation): string => $citation->id, \array_slice($evidence->all(), $before)),
            $error,
        );

        return new ChatToolResult($call->id, $content, $error);
    }

    private function fixed(string $reply, TraceRecorder $trace, Evidence $evidence): TurnOutcome
    {
        return new TurnOutcome([Block::text($reply)], degraded: true, failure: null, trace: $trace->trace(), evidence: $evidence->all());
    }

    /**
     * @return array{list<Block>, list<string>} blocs du brouillon et motifs de rejet
     */
    private function check(ChatResponse $response, AnswerTexts $texts, Evidence $evidence): array
    {
        try {
            $blocks = AnswerFormat::parse($response->text, $texts);
        } catch (\JsonException) {
            return [[], [$response->stopReason === ChatStopReason::MaxTokens
                ? 'La réponse a été coupée avant la fin : fais plus court.'
                : 'La réponse ne respecte pas le format JSON demandé.']];
        }

        // Ne peut être cité que ce que les outils ont donné à lire ou calculé pendant ce tour (D7)
        return [$blocks, DraftValidator::check($blocks, $evidence->references(), $evidence->exhibitReferences())];
    }

    /**
     * Le brouillon cite par référence de tour (S1, S2…) ; le message enregistré cite par
     * identifiant durable, avec les sources composées par le serveur. Un résultat de calcul
     * laisse sa place à la pièce que le serveur a composée : le modèle dit où la montrer, il
     * n'en écrit aucun chiffre. Ce que la réponse n'a pas placé (calcul non présenté, calcul
     * refusé) est montré d'office, avant la question posée à l'utilisateur.
     *
     * @param list<Block> $blocks
     *
     * @return array{list<Block>, list<Citation>} blocs à enregistrer et sources citées, dans l'ordre de leur première citation
     */
    private function resolve(array $blocks, Evidence $evidence): array
    {
        $cited = [];
        $resolved = [];
        $shown = [];
        foreach ($blocks as $block) {
            if ($block->citations() === []) {
                $resolved[] = $block;
                continue;
            }

            if ($block->kind() === StatementKind::CalculationResult) {
                $reference = $block->citations()[0];
                $exhibit = $evidence->exhibitOf($reference);
                // Un même calcul n'est montré qu'une fois
                if ($exhibit !== null && !\in_array($reference, $shown, true)) {
                    $resolved[] = $exhibit->withCaption($block->textContent());
                    $shown[] = $reference;
                }
                continue;
            }

            $ids = [];
            foreach ($block->citations() as $reference) {
                $citation = $evidence->get($reference);
                if ($citation !== null) {
                    $ids[] = $citation->id;
                    $cited[$citation->id] = $citation;
                }
            }
            $resolved[] = $block->withCitations(array_values(array_unique($ids)));
        }

        $unshown = $evidence->unshown($shown);
        if ($unshown !== []) {
            $position = \count($resolved);
            foreach ($resolved as $index => $block) {
                if ($block->type === Block::QUESTION) {
                    $position = $index;
                    break;
                }
            }
            array_splice($resolved, $position, 0, $unshown);
        }

        return [$resolved, array_values($cited)];
    }

    /** @param list<string> $reasons */
    private function retryInstruction(array $reasons): string
    {
        return "[Contrôle automatique d'Hector, pas un message de l'utilisateur]\n"
            . "Ta réponse précédente a été rejetée et n'a pas été montrée à l'utilisateur, pour ces raisons :\n- "
            . implode("\n- ", $reasons)
            . "\nRéponds de nouveau à son dernier message, au même format, en corrigeant ces points.";
    }
}
