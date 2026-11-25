<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Llm\Anthropic;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\RequestOptions;
use App\Application\Conversation\Port\ChatMessage;
use App\Application\Conversation\Port\ChatModelFailedException;
use App\Application\Conversation\Port\ChatModelInterface;
use App\Application\Conversation\Port\ChatModelUnavailableException;
use App\Application\Conversation\Port\ChatRequest;
use App\Application\Conversation\Port\ChatResponse;
use App\Application\Conversation\Port\ChatStopReason;
use App\Application\Conversation\Port\ChatUsage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Psr18Client;

/**
 * Adaptateur Anthropic (Claude) du port ChatModelInterface, sur le SDK officiel
 * (décisions D2 et D3). Aucune classe du SDK ne sort de ce dossier.
 */
final class AnthropicChatModel implements ChatModelInterface
{
    /** Repli côté serveur quand le modèle refuse : le fournisseur choisit le modèle de repli. */
    private const string FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    private ?Client $client;

    public function __construct(
        #[Autowire('%env(CHAT_MODEL)%')]
        private readonly string $model,
        #[Autowire('%env(CHAT_EFFORT)%')]
        private readonly string $effort,
        #[Autowire('%env(int:CHAT_MAX_TOKENS)%')]
        private readonly int $maxTokens,
        #[Autowire('%env(float:CHAT_TIMEOUT_SECONDS)%')]
        private readonly float $timeoutSeconds,
        // .env.local, jamais dans le dépôt ; vide : le SDK cherche un profil `ant auth login`
        #[Autowire('%env(ANTHROPIC_API_KEY)%')]
        private readonly string $apiKey = '',
        // Injecté par les tests de contrat ; sinon créé au premier appel, jamais au démarrage
        ?Client $client = null,
    ) {
        $this->client = $client;
    }

    public function complete(ChatRequest $request): ChatResponse
    {
        try {
            $message = $this->client()->beta->messages->create(
                maxTokens: $this->maxTokens,
                messages: array_map(
                    static fn (ChatMessage $message): array => ['role' => $message->role->value, 'content' => $message->text],
                    $request->messages,
                ),
                model: $this->model,
                // Mise en cache automatique du préfixe (consignes et historique) d'un tour à l'autre
                cacheControl: ['type' => 'ephemeral'],
                fallbacks: 'default',
                outputConfig: $request->outputSchema === null
                    ? ['effort' => $this->effort]
                    : ['effort' => $this->effort, 'format' => ['type' => 'json_schema', 'schema' => $request->outputSchema]],
                system: $request->system,
                betas: [self::FALLBACK_BETA],
            );
        } catch (RateLimitException|InternalServerException $e) {
            // 429, 5xx et surcharge : passager
            throw new ChatModelUnavailableException($this->describe($e), previous: $e);
        } catch (APIStatusException $e) {
            // Autres statuts (clé invalide, requête refusée…) : relancer n'y changera rien
            throw new ChatModelFailedException($this->describe($e), previous: $e);
        } catch (APIConnectionException $e) {
            // Réseau ou délai dépassé
            throw new ChatModelUnavailableException($this->describe($e), previous: $e);
        } catch (APIException $e) {
            throw new ChatModelFailedException($this->describe($e), previous: $e);
        }

        return $this->toResponse($message);
    }

    private function toResponse(BetaMessage $message): ChatResponse
    {
        $texts = [];
        foreach ($message->content as $block) {
            // Les blocs de raisonnement (vides par défaut) et autres types sont ignorés
            if ($block instanceof BetaTextBlock) {
                $texts[] = $block->text;
            }
        }

        return new ChatResponse(
            text: implode("\n\n", $texts),
            stopReason: match ($message->stopReason) {
                'end_turn', 'stop_sequence' => ChatStopReason::EndTurn,
                'max_tokens', 'model_context_window_exceeded' => ChatStopReason::MaxTokens,
                'refusal' => ChatStopReason::Refusal,
                default => ChatStopReason::Other,
            },
            model: $message->model,
            usage: new ChatUsage(
                inputTokens: $message->usage->inputTokens,
                outputTokens: $message->usage->outputTokens,
                cacheReadInputTokens: $message->usage->cacheReadInputTokens ?? 0,
                cacheCreationInputTokens: $message->usage->cacheCreationInputTokens ?? 0,
            ),
        );
    }

    private function client(): Client
    {
        if ($this->client === null) {
            $http = new Psr18Client();
            $this->client = new Client(apiKey: $this->apiKey !== '' ? $this->apiKey : null, requestOptions: RequestOptions::with(
                timeout: $this->timeoutSeconds,
                // Une seule nouvelle tentative : le tour doit rester dans les délais de la requête
                maxRetries: 1,
                transporter: $http,
                uriFactory: $http,
                streamFactory: $http,
                requestFactory: $http,
            ));
        }

        return $this->client;
    }

    /** Classe d'erreur du fournisseur, pour la trace du tour ; jamais montrée à l'utilisateur. */
    private function describe(APIException $e): string
    {
        $type = $e instanceof APIStatusException ? ($e->type->value ?? 'status_error') : 'connection_error';

        return \sprintf('anthropic:%s %s', $type, mb_substr($e->getMessage(), 0, 200));
    }
}
