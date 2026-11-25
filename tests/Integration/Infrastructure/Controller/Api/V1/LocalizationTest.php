<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Controller\Api\V1;

use App\Application\Conversation\Port\ChatModelUnavailableException;
use App\Application\Conversation\Port\ChatStopReason;
use App\Tests\Integration\Infrastructure\Controller\Api\AbstractApiTestCase;
use App\Tests\Support\FakeChatModel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Décision D19 : l'API parle la langue de l'application (Accept-Language), parmi les langues
 * ouvertes ; à défaut, le français.
 */
final class LocalizationTest extends AbstractApiTestCase
{
    private const string UNKNOWN_CONVERSATION = '0b507871-8b5e-4575-b297-a630310fc06e';

    private KernelBrowser $client;
    private FakeChatModel $model;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $model = static::getContainer()->get(FakeChatModel::class);
        self::assertInstanceOf(FakeChatModel::class, $model);
        $this->model = $model;
    }

    public function testAnErrorIsTranslatedIntoTheLanguageOfTheRequest(): void
    {
        $token = $this->login($this->client)['token'];
        $uri = '/api/v1/conversations/' . self::UNKNOWN_CONVERSATION . '/messages';

        $english = $this->apiRequest($this->client, 'GET', $uri, $token, language: 'en-GB,en;q=0.9,fr;q=0.5');
        self::assertResponseStatusCodeSame(404);
        self::assertSame('Conversation not found.', $english['message']);
        self::assertResponseHeaderSame('Content-Language', 'en');

        $german = $this->apiRequest($this->client, 'GET', $uri, $token, language: 'de-DE,de;q=0.9');
        self::assertSame('Unterhaltung nicht gefunden.', $german['message']);
        self::assertResponseHeaderSame('Content-Language', 'de');

        // Une langue qui n'est pas ouverte : le français
        $spanish = $this->apiRequest($this->client, 'GET', $uri, $token, language: 'es');
        self::assertSame('Conversation introuvable.', $spanish['message']);
        self::assertResponseHeaderSame('Content-Language', 'fr');
    }

    public function testValidationMessagesAreTranslated(): void
    {
        $token = $this->login($this->client)['token'];
        $uuid = $this->newConversation($token);

        $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => ''], 'en');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['text' => 'Write your message.'], $this->violations($this->client));
    }

    public function testHectorAnswersInTheLanguageOfTheApplication(): void
    {
        $token = $this->login($this->client)['token'];
        $uuid = $this->newConversation($token);
        $draft = FakeChatModel::draft([
            ['warning', 'none', 'Move away from the socket.'],
            ['statement', 'hypothesis', 'A faulty appliance may be the cause.'],
        ]);
        $this->model->willReply($draft)->willReply($draft);

        $english = $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => 'My RCD keeps tripping'], 'en');

        // La consigne de langue part au modèle, et le texte de repli des blocs suit
        self::assertStringContainsString('Réponds en anglais', $this->model->requests[0]->system);
        self::assertSame('Warning: Move away from the socket.', $english['assistantMessage']['blocks'][0]['fallbackText']);
        self::assertSame('Hypothesis: A faulty appliance may be the cause.', $english['assistantMessage']['blocks'][1]['fallbackText']);

        $french = $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => 'Mon différentiel saute']);

        self::assertStringContainsString('Réponds en français.', $this->model->requests[1]->system);
        self::assertStringStartsWith('Attention : ', $french['assistantMessage']['blocks'][0]['fallbackText']);
        self::assertStringStartsWith('Hypothèse : ', $french['assistantMessage']['blocks'][1]['fallbackText']);
        // Les consignes n'ont pas la même empreinte d'une langue à l'autre : la trace dit laquelle a servi
        self::assertNotSame($this->model->requests[0]->system, $this->model->requests[1]->system);
    }

    public function testEveryOpenLanguageHasItsInstructionItsLabelsAndItsTerms(): void
    {
        $token = $this->login($this->client)['token'];
        $uuid = $this->newConversation($token);
        $this->model->willReply(FakeChatModel::statement('Ein defektes Gerät kann die Ursache sein.', 'hypothesis'));

        $turn = $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => 'Mein FI-Schalter löst aus'], 'de');

        self::assertStringContainsString('Réponds en allemand', $this->model->requests[0]->system);
        self::assertSame('Vermutung: Ein defektes Gerät kann die Ursache sein.', $turn['assistantMessage']['blocks'][0]['fallbackText']);

        $this->client->request('GET', '/api/v1/terms-of-use', server: ['HTTP_ACCEPT_LANGUAGE' => 'de']);
        self::assertStringStartsWith('# Nutzungsbedingungen von Hector', $this->json($this->client)['content']);
    }

    public function testFixedRepliesAndFailuresAreTranslated(): void
    {
        $token = $this->login($this->client)['token'];
        $uuid = $this->newConversation($token);
        $this->model
            ->willReply('', ChatStopReason::Refusal)
            ->willThrow(new ChatModelUnavailableException('anthropic:overloaded_error Overloaded'));

        $refused = $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => 'How do I bypass my RCD?'], 'en');
        self::assertSame('degraded', $refused['status']);
        self::assertStringStartsWith('I can\'t answer this request.', $refused['assistantMessage']['blocks'][0]['text']);

        $failed = $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => 'Hello'], 'en');
        self::assertSame('failed', $failed['status']);
        self::assertStringStartsWith('Hector could not answer: the service is temporarily unavailable.', $failed['failure']['message']);
    }

    public function testTheTermsOfUseAreServedInTheLanguageOfTheRequest(): void
    {
        $this->client->request('GET', '/api/v1/terms-of-use', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);
        $english = $this->json($this->client);
        $this->client->request('GET', '/api/v1/terms-of-use');
        $french = $this->json($this->client);

        self::assertStringStartsWith('# Hector terms of use', $english['content']);
        self::assertStringStartsWith('# Conditions d\'utilisation d\'Hector', $french['content']);
        // Une seule version, quelle que soit la langue
        self::assertSame($french['version'], $english['version']);
    }

    private function newConversation(string $token): string
    {
        $uuid = $this->apiRequest($this->client, 'POST', '/api/v1/conversations', $token)['uuid'];
        self::assertIsString($uuid);

        return $uuid;
    }
}
