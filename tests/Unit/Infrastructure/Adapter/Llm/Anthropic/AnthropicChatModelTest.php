<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Adapter\Llm\Anthropic;

use Anthropic\Client;
use Anthropic\RequestOptions;
use App\Application\Conversation\Port\ChatMessage;
use App\Application\Conversation\Port\ChatModelFailedException;
use App\Application\Conversation\Port\ChatModelUnavailableException;
use App\Application\Conversation\Port\ChatRequest;
use App\Application\Conversation\Port\ChatRole;
use App\Application\Conversation\Port\ChatStopReason;
use App\Infrastructure\Adapter\Llm\Anthropic\AnthropicChatModel;
use App\Tests\Support\RecordedHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

/**
 * Contrat de l'adaptateur : traduction entre nos objets et l'API du fournisseur, sur des
 * réponses rejouées (docs/ARCHITECTURE.md, § 12). Les réponses suivent le format documenté ;
 * les remplacer par des réponses réellement enregistrées au premier test vivant.
 */
final class AnthropicChatModelTest extends TestCase
{
    private const string RESPONSES = __DIR__ . '/responses';

    public function testSendsTheRequestAndTranslatesTheAnswer(): void
    {
        $http = RecordedHttpClient::replying(200, self::RESPONSES . '/end_turn.json');

        $response = $this->model($http)->complete(new ChatRequest('Consignes', [
            new ChatMessage(ChatRole::User, 'Question 1'),
            new ChatMessage(ChatRole::Assistant, 'Réponse 1'),
            new ChatMessage(ChatRole::User, 'Mon différentiel saute'),
        ]));

        // Seul le texte compte ; le bloc de raisonnement (vide) est ignoré
        self::assertSame('Commencez par noter à quel moment le différentiel déclenche.', $response->text);
        self::assertSame(ChatStopReason::EndTurn, $response->stopReason);
        self::assertSame('claude-opus-5-5', $response->model);
        self::assertSame(1200, $response->usage->inputTokens);
        self::assertSame(85, $response->usage->outputTokens);
        self::assertSame(1000, $response->usage->cacheReadInputTokens);
        self::assertSame(150, $response->usage->cacheCreationInputTokens);

        $request = $http->requests[0];
        self::assertSame('POST', $request->getMethod());
        self::assertStringEndsWith('/v1/messages', $request->getUri()->getPath());
        self::assertStringContainsString('server-side-fallback-2026-07-01', $request->getHeaderLine('anthropic-beta'));
        $body = json_decode((string) $request->getBody(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('claude-opus-5-5', $body['model']);
        self::assertSame(16000, $body['max_tokens']);
        self::assertSame('Consignes', $body['system']);
        self::assertSame('default', $body['fallbacks']);
        self::assertSame(['effort' => 'medium'], $body['output_config']);
        self::assertSame(['type' => 'ephemeral'], $body['cache_control']);
        self::assertSame([
            ['role' => 'user', 'content' => 'Question 1'],
            ['role' => 'assistant', 'content' => 'Réponse 1'],
            ['role' => 'user', 'content' => 'Mon différentiel saute'],
        ], $body['messages']);
        // Ni température, ni choix d'outil forcé, ni préremplissage (docs/ARCHITECTURE.md, § 7)
        self::assertArrayNotHasKey('temperature', $body);
        self::assertArrayNotHasKey('tool_choice', $body);
    }

    public function testARefusalIsReported(): void
    {
        $response = $this->model(RecordedHttpClient::replying(200, self::RESPONSES . '/refusal.json'))->complete($this->request());

        self::assertSame(ChatStopReason::Refusal, $response->stopReason);
        self::assertSame('', $response->text);
    }

    public function testAnOverloadedProviderIsUnavailable(): void
    {
        $this->expectException(ChatModelUnavailableException::class);
        $this->expectExceptionMessage('anthropic:overloaded_error');

        $this->model(RecordedHttpClient::replying(529, self::RESPONSES . '/overloaded.json'))->complete($this->request());
    }

    public function testAnInvalidKeyIsAFailureNotARetry(): void
    {
        $this->expectException(ChatModelFailedException::class);
        $this->expectExceptionMessage('anthropic:authentication_error');

        $this->model(RecordedHttpClient::replying(401, self::RESPONSES . '/authentication_error.json'))->complete($this->request());
    }

    public function testANetworkFailureIsUnavailable(): void
    {
        $this->expectException(ChatModelUnavailableException::class);

        $this->model(RecordedHttpClient::offline())->complete($this->request());
    }

    private function model(RecordedHttpClient $http): AnthropicChatModel
    {
        $factory = new Psr17Factory();
        $client = new Client(apiKey: 'cle-de-test', requestOptions: RequestOptions::with(
            maxRetries: 0,
            transporter: $http,
            uriFactory: $factory,
            streamFactory: $factory,
            requestFactory: $factory,
        ));

        return new AnthropicChatModel('claude-opus-5-5', 'medium', 16000, 90.0, client: $client);
    }

    private function request(): ChatRequest
    {
        return new ChatRequest('Consignes', [new ChatMessage(ChatRole::User, 'Bonjour')]);
    }
}
