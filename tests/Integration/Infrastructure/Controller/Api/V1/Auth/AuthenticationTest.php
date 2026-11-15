<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Controller\Api\V1\Auth;

use App\Infrastructure\Persistence\Doctrine\Fixtures\UserFixture;
use App\Tests\Integration\Infrastructure\Controller\Api\AbstractApiTestCase;

final class AuthenticationTest extends AbstractApiTestCase
{
    public function testLoginReturnsAccessAndRefreshTokens(): void
    {
        $client = static::createClient();

        $tokens = $this->login($client);

        self::assertNotEmpty($tokens['token']);
        self::assertNotEmpty($tokens['refresh_token']);
    }

    public function testLoginIsCaseInsensitiveOnEmail(): void
    {
        $client = static::createClient();

        $tokens = $this->login($client, strtoupper(UserFixture::USER_EMAIL));

        self::assertNotEmpty($tokens['token']);
    }

    public function testLoginWithWrongPasswordIsRejected(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => UserFixture::USER_EMAIL,
            'password' => 'mauvais-mot-de-passe',
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(401, $this->json($client)['code']);
    }

    public function testLoginWithUnknownEmailIsRejected(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => 'inconnu@hector.test',
            'password' => UserFixture::USER_PASSWORD,
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testMeRequiresAToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/auth/me');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(401, $this->json($client)['code']);
    }

    public function testMeRejectsAnInvalidToken(): void
    {
        $client = static::createClient();

        $this->apiRequest($client, 'GET', '/api/v1/auth/me', 'ceci-n-est-pas-un-jwt');

        self::assertResponseStatusCodeSame(401);
    }

    public function testMeReturnsTheProfile(): void
    {
        $client = static::createClient();
        $tokens = $this->login($client);

        $profile = $this->apiRequest($client, 'GET', '/api/v1/auth/me', $tokens['token']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([
            'uuid' => UserFixture::USER_UUID,
            'email' => UserFixture::USER_EMAIL,
            'fullName' => 'Jean ÉLECTRICIEN',
            'roles' => ['ROLE_USER'],
            'professional' => true,
            'companyName' => 'Élec Chantier',
            'siret' => UserFixture::COMPANY_SIRET,
            'vatNumber' => 'FR12345678901',
        ], $profile);
    }

    public function testRefreshRotatesTheRefreshToken(): void
    {
        $client = static::createClient();
        $tokens = $this->login($client);

        $client->jsonRequest('POST', '/api/v1/auth/token/refresh', ['refresh_token' => $tokens['refresh_token']]);
        self::assertResponseStatusCodeSame(200);
        $renewed = $this->json($client);

        self::assertNotSame($tokens['refresh_token'], $renewed['refresh_token']);
        $this->apiRequest($client, 'GET', '/api/v1/auth/me', $renewed['token']);
        self::assertResponseStatusCodeSame(200);

        // Usage unique : l'ancien refresh token ne fonctionne plus
        $client->jsonRequest('POST', '/api/v1/auth/token/refresh', ['refresh_token' => $tokens['refresh_token']]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testRefreshWithAnUnknownTokenIsRejected(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/auth/token/refresh', ['refresh_token' => 'inconnu']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testLogoutRevokesTheRefreshToken(): void
    {
        $client = static::createClient();
        $tokens = $this->login($client);

        $client->jsonRequest('POST', '/api/v1/auth/token/invalidate', ['refresh_token' => $tokens['refresh_token']]);
        self::assertResponseStatusCodeSame(200);

        $client->jsonRequest('POST', '/api/v1/auth/token/refresh', ['refresh_token' => $tokens['refresh_token']]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testLogoutWithoutRefreshTokenIsABadRequest(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/auth/token/invalidate', []);

        self::assertResponseStatusCodeSame(400);
    }

    public function testUnknownRouteIsAJsonNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/nimporte-quoi');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(404, $this->json($client)['code']);
    }
}
