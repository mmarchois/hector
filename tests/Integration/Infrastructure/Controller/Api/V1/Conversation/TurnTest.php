<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Controller\Api\V1\Conversation;

use App\Application\Conversation\Port\ChatModelUnavailableException;
use App\Application\Conversation\Port\ChatUsage;
use App\Application\Conversation\Service\TurnRunner;
use App\Infrastructure\Persistence\Doctrine\Fixtures\UserFixture;
use App\Tests\Integration\Infrastructure\Controller\Api\AbstractApiTestCase;
use App\Tests\Support\FakeChatModel;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/** Lot L5 : un tour réel, avec le faux modèle (make test n'appelle jamais le fournisseur). */
final class TurnTest extends AbstractApiTestCase
{
    private KernelBrowser $client;
    private FakeChatModel $model;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Le faux modèle doit survivre d'une requête à l'autre pour être programmé
        $this->client->disableReboot();
        $model = static::getContainer()->get(FakeChatModel::class);
        self::assertInstanceOf(FakeChatModel::class, $model);
        $this->model = $model;
    }

    public function testTheModelAnswersAndTheTurnIsTraced(): void
    {
        $token = $this->login($this->client)['token'];
        $uuid = $this->newConversation($token);
        $this->model->willReply(FakeChatModel::statement('Notez d\'abord à quel moment le différentiel déclenche.', 'recommendation'));

        $turn = $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => 'Mon différentiel saute']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('completed', $turn['status']);
        self::assertNull($turn['failure']);
        self::assertSame('Notez d\'abord à quel moment le différentiel déclenche.', $turn['assistantMessage']['blocks'][0]['text']);

        // CV6 : modèle, version des consignes, jetons, durée, issue
        $trace = $this->connection()->fetchAssociative('SELECT * FROM conversation_turn WHERE uuid = ?', [$turn['uuid']]);
        self::assertIsArray($trace);
        self::assertSame('fake-model', $trace['trace_model']);
        self::assertSame('end_turn', $trace['trace_stop_reason']);
        self::assertSame(100, $trace['trace_input_tokens']);
        self::assertSame(20, $trace['trace_output_tokens']);
        self::assertSame(12, \strlen((string) $trace['trace_prompt_version']));
        self::assertNotNull($trace['trace_duration_ms']);
    }

    public function testAStructuredAnswerKeepsTheNatureOfEachStatement(): void
    {
        $token = $this->login($this->client)['token'];
        $uuid = $this->newConversation($token);
        $this->model->willReply(FakeChatModel::draft([
            ['statement', 'hypothesis', 'Un appareil défectueux peut provoquer le déclenchement.'],
            ['question', 'none', 'Le déclenchement suit-il la mise en route d\'un appareil ?'],
        ]));

        $turn = $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => 'Mon différentiel saute']);

        self::assertSame([
            [
                'type' => 'statement',
                'version' => 1,
                'kind' => 'hypothesis',
                'text' => 'Un appareil défectueux peut provoquer le déclenchement.',
                'citations' => [],
                'fallbackText' => 'Hypothèse : Un appareil défectueux peut provoquer le déclenchement.',
            ],
            [
                'type' => 'question',
                'version' => 1,
                'text' => 'Le déclenchement suit-il la mise en route d\'un appareil ?',
                'fallbackText' => 'Le déclenchement suit-il la mise en route d\'un appareil ?',
            ],
        ], $turn['assistantMessage']['blocks']);
        // La sortie structurée est demandée au modèle
        self::assertNotNull($this->model->requests[0]->outputSchema);
    }

    public function testARejectedDraftIsRetriedOnceAndNeverShown(): void
    {
        $token = $this->login($this->client)['token'];
        $uuid = $this->newConversation($token);
        $this->model
            ->willReply(FakeChatModel::statement('Le différentiel doit être de 30 mA (article 411.3.3).', 'sourced_fact'))
            ->willReply(FakeChatModel::statement('Un différentiel 30 mA protège les personnes contre les contacts indirects.'));

        $turn = $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => 'Quel différentiel ?']);

        self::assertSame('completed', $turn['status']);
        self::assertSame('Un différentiel 30 mA protège les personnes contre les contacts indirects.', $turn['assistantMessage']['blocks'][0]['text']);
        $shown = json_encode($this->apiRequest($this->client, 'GET', "/api/v1/conversations/$uuid/messages", $token), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('411.3.3', $shown);

        // La reprise reçoit le brouillon et les motifs du rejet ; la trace les garde
        $retry = $this->model->requests[1]->messages;
        self::assertSame('assistant', $retry[\count($retry) - 2]->role->value);
        self::assertStringContainsString('Contrôle automatique', $retry[\count($retry) - 1]->text);
        $rejections = $this->rejections($turn['uuid']);
        self::assertCount(1, $rejections);
        self::assertSame(1, $rejections[0]['attempt']);
        self::assertCount(2, $rejections[0]['reasons']);
        // Jetons des deux appels
        self::assertSame(200, $this->connection()->fetchOne('SELECT trace_input_tokens FROM conversation_turn WHERE uuid = ?', [$turn['uuid']]));
    }

    public function testTwoRejectedDraftsGiveADegradedAnswerThatSaysSo(): void
    {
        $token = $this->login($this->client)['token'];
        $uuid = $this->newConversation($token);
        $this->model
            ->willReply(FakeChatModel::statement('Voir le tableau 52H.'))
            ->willReply('Ce n\'est pas du JSON.');

        $turn = $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => 'Quelle section ?']);

        self::assertSame('degraded', $turn['status']);
        self::assertSame(TurnRunner::DEGRADED_REPLY, $turn['assistantMessage']['blocks'][0]['text']);
        self::assertCount(2, $this->model->requests);
        self::assertSame([1, 2], array_column($this->rejections($turn['uuid']), 'attempt'));
    }

    public function testTheModelReceivesTheHistoryAndNoIdentity(): void
    {
        $token = $this->login($this->client)['token'];
        $uuid = $this->newConversation($token);
        $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => 'Première question']);
        $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => 'Seconde question']);

        $request = $this->model->requests[1];
        self::assertSame(['user', 'assistant', 'user'], array_map(static fn ($message): string => $message->role->value, $request->messages));
        self::assertSame('Seconde question', $request->messages[2]->text);
        // Consignes adaptées à un professionnel
        self::assertStringContainsString('Un professionnel de l\'électricité.', $request->system);

        // Aucune donnée d'identité ne part chez le fournisseur
        $sent = $request->system . implode("\n", array_map(static fn ($message): string => $message->text, $request->messages));
        foreach ([UserFixture::USER_EMAIL, 'Jean ÉLECTRICIEN', UserFixture::COMPANY_SIRET, 'Élec Chantier', UserFixture::USER_UUID] as $identity) {
            self::assertStringNotContainsString($identity, $sent);
        }
    }

    public function testAnUnavailableProviderFailsTheTurnWithAClearMessageAndTheUserCanRetry(): void
    {
        $token = $this->login($this->client)['token'];
        $uuid = $this->newConversation($token);
        $this->model->willThrow(new ChatModelUnavailableException('anthropic:overloaded_error Overloaded'));

        $failed = $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => 'Bonjour']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('failed', $failed['status']);
        self::assertNull($failed['assistantMessage']);
        self::assertSame('unavailable', $failed['failure']['reason']);
        self::assertSame('Hector n\'a pas pu répondre : le service est momentanément indisponible. Réessayez dans un instant.', $failed['failure']['message']);
        // Le détail technique reste dans la trace, jamais dans la réponse
        self::assertStringNotContainsString('overloaded', (string) json_encode($failed));

        $retried = $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => 'Bonjour']);
        self::assertSame('completed', $retried['status']);
    }

    public function testAGuestMustSignUpOnceItsAllowanceIsUsed(): void
    {
        $guest = $this->openGuestAccount($this->client)['token'];
        $uuid = $this->newConversation($guest);
        // Allocation de test : 3 messages pour un invité
        for ($i = 1; $i <= 3; ++$i) {
            $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $guest, ['text' => "Question $i"]);
            self::assertResponseStatusCodeSame(201);
        }
        $callsBefore = \count($this->model->requests);

        $refused = $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $guest, ['text' => 'Question 4']);

        self::assertResponseStatusCodeSame(429);
        self::assertSame('signup_required', $refused['reason']);
        self::assertSame('Vous avez atteint la limite de l\'essai sans compte. Créez un compte gratuit pour continuer.', $refused['message']);
        // CV8 : refusé avant tout appel au modèle, et rien n'est enregistré
        self::assertCount($callsBefore, $this->model->requests);
        $messages = $this->apiRequest($this->client, 'GET', "/api/v1/conversations/$uuid/messages", $guest);
        self::assertCount(6, $messages['items']);
    }

    public function testAnAccountHasADailyAllowance(): void
    {
        $token = $this->login($this->client, UserFixture::INDIVIDUAL_EMAIL)['token'];
        $uuid = $this->newConversation($token);
        // Allocation de test : 5 messages par 24 heures pour un compte
        for ($i = 1; $i <= 5; ++$i) {
            $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => "Question $i"]);
        }

        $refused = $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => 'Question 6']);

        self::assertResponseStatusCodeSame(429);
        self::assertSame('free_allowance_exhausted', $refused['reason']);
    }

    public function testAGuestWhoseAnswersAreLongRunsOutOfTokens(): void
    {
        $guest = $this->openGuestAccount($this->client)['token'];
        $uuid = $this->newConversation($guest);
        // Allocation de test : 50 000 jetons pour un invité, atteints dès le premier tour
        $this->model->willReply(FakeChatModel::statement('Longue réponse'), usage: new ChatUsage(30_000, 15_000, 5_000));
        $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $guest, ['text' => 'Question 1']);
        self::assertResponseStatusCodeSame(201);

        $refused = $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $guest, ['text' => 'Question 2']);

        self::assertResponseStatusCodeSame(429);
        self::assertSame('signup_required', $refused['reason']);
    }

    public function testEveryoneIsRefusedOnceTheGlobalBudgetIsReached(): void
    {
        $token = $this->login($this->client)['token'];
        $uuid = $this->newConversation($token);
        // Budget global de test : 1 000 000 de jetons sur 24 heures (.env.test)
        $this->model->willReply(FakeChatModel::statement('Réponse'), usage: new ChatUsage(1_000_000, 0));
        $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => 'Question']);
        $callsBefore = \count($this->model->requests);

        $other = $this->login($this->client, UserFixture::INDIVIDUAL_EMAIL)['token'];
        $refused = $this->apiRequest($this->client, 'POST', '/api/v1/conversations/' . $this->newConversation($other) . '/messages', $other, ['text' => 'Bonjour']);

        self::assertResponseStatusCodeSame(503);
        self::assertSame('service_saturated', $refused['reason']);
        self::assertStringContainsString('18 ou 112', $refused['message']);
        // CV9 : refusé avant tout appel au modèle
        self::assertCount($callsBefore, $this->model->requests);
    }

    private function newConversation(string $token): string
    {
        $uuid = $this->apiRequest($this->client, 'POST', '/api/v1/conversations', $token)['uuid'];
        self::assertIsString($uuid);

        return $uuid;
    }

    /** @return list<array{attempt: int, reasons: list<string>}> */
    private function rejections(string $turnUuid): array
    {
        $json = $this->connection()->fetchOne('SELECT trace_rejections FROM conversation_turn WHERE uuid = ?', [$turnUuid]);
        self::assertIsString($json);

        return json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
