<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Controller\Api\V1\Conversation;

use App\Infrastructure\Persistence\Doctrine\Fixtures\UserFixture;
use App\Tests\Integration\Infrastructure\Controller\Api\AbstractApiTestCase;
use App\Tests\Support\FakeChatModel;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/** Lot L4 : conversations et messages, sans IA (règles CV1 à CV5). */
final class ConversationTest extends AbstractApiTestCase
{
    public function testAConversationIsCreatedEmpty(): void
    {
        $client = static::createClient();
        $token = $this->login($client)['token'];

        $conversation = $this->apiRequest($client, 'POST', '/api/v1/conversations', $token);

        self::assertResponseStatusCodeSame(201);
        self::assertNotEmpty($conversation['uuid']);
        self::assertNull($conversation['title']);
        self::assertSame($conversation['createdAt'], $conversation['updatedAt']);
    }

    public function testSendingAMessageReturnsTheFinishedTurnAndItsAnswer(): void
    {
        $client = static::createClient();
        $token = $this->login($client)['token'];
        $uuid = $this->newConversation($client, $token);

        $turn = $this->apiRequest($client, 'POST', "/api/v1/conversations/$uuid/messages", $token, [
            'text' => '  Un différentiel 30 mA déclenche de façon aléatoire.  ',
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame($uuid, $turn['conversationUuid']);
        self::assertSame('completed', $turn['status']);
        self::assertNotNull($turn['finishedAt']);
        self::assertSame('user', $turn['userMessage']['role']);
        self::assertSame([[
            'type' => 'text',
            'version' => 1,
            'text' => 'Un différentiel 30 mA déclenche de façon aléatoire.',
            'fallbackText' => 'Un différentiel 30 mA déclenche de façon aléatoire.',
        ]], $turn['userMessage']['blocks']);
        self::assertSame('assistant', $turn['assistantMessage']['role']);
        self::assertSame(FakeChatModel::DEFAULT_REPLY, $turn['assistantMessage']['blocks'][0]['fallbackText']);
        self::assertSame([], $turn['assistantMessage']['citations']);

        // Le tour se relit, et le premier message titre la conversation
        $followed = $this->apiRequest($client, 'GET', '/api/v1/turns/' . $turn['uuid'], $token);
        self::assertResponseStatusCodeSame(200);
        self::assertSame($turn, $followed);

        $list = $this->apiRequest($client, 'GET', '/api/v1/conversations', $token);
        self::assertSame('Un différentiel 30 mA déclenche de façon aléatoire.', $list['items'][0]['title']);
    }

    public function testMessagesArePagedBackwardsInTime(): void
    {
        $client = static::createClient();
        $token = $this->login($client)['token'];
        $uuid = $this->newConversation($client, $token);
        foreach (['Premier', 'Deuxième', 'Troisième'] as $text) {
            $this->apiRequest($client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => $text]);
        }

        // Les 4 plus récents, du plus ancien au plus récent
        $latest = $this->apiRequest($client, 'GET', "/api/v1/conversations/$uuid/messages?limit=4", $token);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['Deuxième', null, 'Troisième', null], $this->userTexts($latest['items']));
        self::assertSame(['user', 'assistant', 'user', 'assistant'], array_column($latest['items'], 'role'));
        self::assertTrue($latest['hasMore']);

        $older = $this->apiRequest($client, 'GET', "/api/v1/conversations/$uuid/messages?limit=4&before=" . $latest['items'][0]['uuid'], $token);
        self::assertSame(['Premier', null], $this->userTexts($older['items']));
        self::assertFalse($older['hasMore']);
    }

    public function testInvalidPagingIsRejected(): void
    {
        $client = static::createClient();
        $token = $this->login($client)['token'];
        $uuid = $this->newConversation($client, $token);

        $this->apiRequest($client, 'GET', "/api/v1/conversations/$uuid/messages?limit=0", $token);
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('limit', $this->violations($client));

        $this->apiRequest($client, 'GET', "/api/v1/conversations/$uuid/messages?before=pas-un-uuid", $token);
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('before', $this->violations($client));

        $this->apiRequest($client, 'GET', '/api/v1/conversations?page=0', $token);
        self::assertResponseStatusCodeSame(422);

        $this->apiRequest($client, 'GET', '/api/v1/conversations?page=abc', $token);
        self::assertResponseStatusCodeSame(422);
    }

    public function testAnEmptyOrTooLongMessageIsRejected(): void
    {
        $client = static::createClient();
        $token = $this->login($client)['token'];
        $uuid = $this->newConversation($client, $token);

        $this->apiRequest($client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => '   ']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['text' => 'Écrivez votre message.'], $this->violations($client));

        $this->apiRequest($client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => str_repeat('a', 4001)]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['text' => 'Votre message ne doit pas dépasser 4000 caractères.'], $this->violations($client));

        // Rien n'a été enregistré
        $messages = $this->apiRequest($client, 'GET', "/api/v1/conversations/$uuid/messages", $token);
        self::assertSame([], $messages['items']);
    }

    public function testAnotherUserCanNeitherReadNorFeedTheConversation(): void
    {
        $client = static::createClient();
        $owner = $this->login($client)['token'];
        $uuid = $this->newConversation($client, $owner);
        $turn = $this->apiRequest($client, 'POST', "/api/v1/conversations/$uuid/messages", $owner, ['text' => 'Secret']);

        $intruder = $this->login($client, UserFixture::INDIVIDUAL_EMAIL)['token'];

        // CV1 : 404, comme pour une conversation qui n'existe pas
        $this->apiRequest($client, 'GET', "/api/v1/conversations/$uuid/messages", $intruder);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('Conversation introuvable.', $this->json($client)['message']);
        $this->apiRequest($client, 'POST', "/api/v1/conversations/$uuid/messages", $intruder, ['text' => 'Intrusion']);
        self::assertResponseStatusCodeSame(404);
        $this->apiRequest($client, 'GET', '/api/v1/turns/' . $turn['uuid'], $intruder);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->apiRequest($client, 'GET', '/api/v1/conversations', $intruder)['total']);

        // La conversation n'a pas bougé
        $messages = $this->apiRequest($client, 'GET', "/api/v1/conversations/$uuid/messages", $owner);
        self::assertCount(2, $messages['items']);
    }

    public function testUnknownConversationsAndTurnsAreNotFound(): void
    {
        $client = static::createClient();
        $token = $this->login($client)['token'];

        $this->apiRequest($client, 'GET', '/api/v1/conversations/0b507871-0000-4000-8000-000000000000/messages', $token);
        self::assertResponseStatusCodeSame(404);
        $this->apiRequest($client, 'GET', '/api/v1/conversations/pas-un-uuid/messages', $token);
        self::assertResponseStatusCodeSame(404);
        $this->apiRequest($client, 'GET', '/api/v1/turns/0b507871-0000-4000-8000-000000000000', $token);
        self::assertResponseStatusCodeSame(404);
    }

    public function testConversationsRequireAuthentication(): void
    {
        $client = static::createClient();
        $client->jsonRequest('GET', '/api/v1/conversations');

        self::assertResponseStatusCodeSame(401);
    }

    public function testTheMostRecentlyActiveConversationComesFirst(): void
    {
        $client = static::createClient();
        $token = $this->login($client)['token'];
        $first = $this->newConversation($client, $token);
        $second = $this->newConversation($client, $token);
        $connection = $this->connection();
        // Activité différente à la seconde près : la plus ancienne reçoit le dernier message
        $connection->executeStatement("UPDATE conversation SET updated_at = now() - interval '1 hour' WHERE uuid IN (?, ?)", [$first, $second]);
        $this->apiRequest($client, 'POST', "/api/v1/conversations/$first/messages", $token, ['text' => 'Relance']);

        $list = $this->apiRequest($client, 'GET', '/api/v1/conversations', $token);

        self::assertSame([$first, $second], array_column($list['items'], 'uuid'));
        self::assertSame(1, $list['page']);
        self::assertSame(20, $list['perPage']);
        self::assertSame(2, $list['total']);
    }

    public function testDeletingTheAccountDeletesItsConversations(): void
    {
        $client = static::createClient();
        $token = $this->login($client)['token'];
        $uuid = $this->newConversation($client, $token);
        $this->apiRequest($client, 'POST', "/api/v1/conversations/$uuid/messages", $token, ['text' => 'Bonjour']);

        $this->apiRequest($client, 'DELETE', '/api/v1/auth/me', $token);
        self::assertResponseStatusCodeSame(204);

        $connection = $this->connection();
        self::assertSame(0, (int) $connection->fetchOne('SELECT count(*) FROM conversation WHERE uuid = ?', [$uuid]));
        self::assertSame(0, (int) $connection->fetchOne('SELECT count(*) FROM conversation_message WHERE conversation_uuid = ?', [$uuid]));
        self::assertSame(0, (int) $connection->fetchOne('SELECT count(*) FROM conversation_turn WHERE conversation_uuid = ?', [$uuid]));
    }

    public function testAGuestWhoLogsInKeepsItsConversations(): void
    {
        $client = static::createClient();
        $guest = $this->openGuestAccount($client);
        $guestUuid = $this->apiRequest($client, 'GET', '/api/v1/auth/me', $guest['token'])['uuid'];
        $uuid = $this->newConversation($client, $guest['token']);
        $this->apiRequest($client, 'POST', "/api/v1/conversations/$uuid/messages", $guest['token'], ['text' => 'Ma question d\'invité']);

        // L'invité se connecte à son compte, puis l'application demande le rattachement (P11)
        $account = $this->login($client)['token'];
        $this->apiRequest($client, 'POST', '/api/v1/auth/merge-guest', $account, ['guestRefreshToken' => $guest['refresh_token']]);
        self::assertResponseStatusCodeSame(204);

        $list = $this->apiRequest($client, 'GET', '/api/v1/conversations', $account);
        self::assertSame([$uuid], array_column($list['items'], 'uuid'));
        $messages = $this->apiRequest($client, 'GET', "/api/v1/conversations/$uuid/messages", $account);
        self::assertSame(['Ma question d\'invité', null], $this->userTexts($messages['items']));

        // Le compte invité n'existe plus, ni sa session
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT count(*) FROM "user" WHERE uuid = ?', [$guestUuid]));
        $client->jsonRequest('POST', '/api/v1/auth/token/refresh', ['refresh_token' => $guest['refresh_token']]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testOnlyAValidGuestSessionCanBeMerged(): void
    {
        $client = static::createClient();
        $account = $this->login($client)['token'];

        $this->apiRequest($client, 'POST', '/api/v1/auth/merge-guest', $account, ['guestRefreshToken' => 'inconnu']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['guestRefreshToken' => 'Session d\'invité introuvable ou expirée.'], $this->violations($client));

        // La session d'un compte complet ne se fond jamais dans un autre compte
        $member = $this->login($client, UserFixture::INDIVIDUAL_EMAIL);
        $this->apiRequest($client, 'POST', '/api/v1/auth/merge-guest', $account, ['guestRefreshToken' => $member['refresh_token']]);
        self::assertResponseStatusCodeSame(422);
        $this->apiRequest($client, 'GET', '/api/v1/auth/me', $member['token']);
        self::assertResponseStatusCodeSame(200);
    }

    private function newConversation(KernelBrowser $client, string $token): string
    {
        $uuid = $this->apiRequest($client, 'POST', '/api/v1/conversations', $token)['uuid'];
        self::assertIsString($uuid);

        return $uuid;
    }

    /**
     * Texte des messages de l'utilisateur, null pour ceux de l'assistant.
     *
     * @param list<array<string, mixed>> $messages
     *
     * @return list<?string>
     */
    private function userTexts(array $messages): array
    {
        return array_map(
            static fn (array $message): ?string => $message['role'] === 'user' ? $message['blocks'][0]['text'] : null,
            $messages,
        );
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
