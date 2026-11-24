<?php

declare(strict_types=1);

namespace App\Tests\Live;

use App\Application\Conversation\Port\ChatMessage;
use App\Application\Conversation\Port\ChatRequest;
use App\Application\Conversation\Port\ChatRole;
use App\Application\Conversation\Port\ChatStopReason;
use App\Infrastructure\Adapter\Llm\Anthropic\AnthropicChatModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Test vivant : un vrai appel au fournisseur, payant. Lancé à la main (make test_live),
 * jamais par make test. Clé dans .env.local : ANTHROPIC_API_KEY=sk-ant-...
 */
final class AnthropicChatModelLiveTest extends TestCase
{
    public function testTheProviderAnswersInFrench(): void
    {
        $localEnv = \dirname(__DIR__, 2) . '/.env.local';
        $env = is_file($localEnv) ? (new Dotenv())->parse((string) file_get_contents($localEnv)) : [];
        $apiKey = $env['ANTHROPIC_API_KEY'] ?? '';
        if ($apiKey === '') {
            self::markTestSkipped('ANTHROPIC_API_KEY absente de .env.local.');
        }

        $model = new AnthropicChatModel($env['CHAT_MODEL'] ?? 'claude-opus-5-5', $env['CHAT_EFFORT'] ?? 'medium', 1024, 90.0, apiKey: $apiKey);
        $response = $model->complete(new ChatRequest(
            'Réponds en français, en une phrase.',
            [new ChatMessage(ChatRole::User, 'À quoi sert un dispositif différentiel ?')],
        ));

        self::assertSame(ChatStopReason::EndTurn, $response->stopReason);
        self::assertNotSame('', $response->text);
        self::assertGreaterThan(0, $response->usage->inputTokens);
        fwrite(\STDERR, \sprintf("\n[%s] %s\n(%d jetons en entrée, %d en sortie)\n", $response->model, $response->text, $response->usage->inputTokens, $response->usage->outputTokens));
    }
}
