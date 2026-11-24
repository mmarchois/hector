<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiDocTest extends WebTestCase
{
    public function testOpenApiDocListsApiRoutes(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/doc');

        self::assertResponseStatusCodeSame(200);
        $doc = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('Hector API', $doc['info']['title']);
        self::assertArrayHasKey('/api/v1/auth/register', $doc['paths']);
        self::assertArrayHasKey('/api/v1/auth/guest', $doc['paths']);
        self::assertArrayHasKey('/api/v1/terms-of-use', $doc['paths']);
        self::assertSame([], $doc['paths']['/api/v1/auth/guest']['post']['security']);
        self::assertArrayHasKey('CreateGuestRequest', $doc['components']['schemas']);
        // Conversations (lot L4)
        self::assertEqualsCanonicalizing(['get', 'post'], array_keys($doc['paths']['/api/v1/conversations']));
        self::assertEqualsCanonicalizing(['get', 'post'], array_keys($doc['paths']['/api/v1/conversations/{uuid}/messages']));
        self::assertArrayHasKey('/api/v1/turns/{uuid}', $doc['paths']);
        self::assertArrayHasKey('/api/v1/auth/merge-guest', $doc['paths']);
        self::assertArrayHasKey('TurnResponse', $doc['components']['schemas']);
        self::assertArrayHasKey('MessageResponse', $doc['components']['schemas']);
        self::assertSame(['get', 'put', 'delete'], array_keys($doc['paths']['/api/v1/auth/me']));
        // Routes d'auth documentées statiquement (pas de contrôleur)
        self::assertArrayHasKey('/api/v1/auth/login', $doc['paths']);
        self::assertArrayHasKey('/api/v1/auth/token/refresh', $doc['paths']);
        self::assertArrayHasKey('/api/v1/auth/token/invalidate', $doc['paths']);
        // Schémas exposés pour le client mobile
        self::assertArrayHasKey('RegisterRequest', $doc['components']['schemas']);
        self::assertArrayHasKey('UpdateProfileRequest', $doc['components']['schemas']);
        self::assertArrayHasKey('ProfileResponse', $doc['components']['schemas']);
        self::assertArrayHasKey('bearerAuth', $doc['components']['securitySchemes']);
        // L'inscription est publique : pas de cadenas dans Swagger
        self::assertSame([], $doc['paths']['/api/v1/auth/register']['post']['security']);
    }

    public function testSwaggerUiIsServed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/doc');

        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('swagger-ui', (string) $client->getResponse()->getContent());
    }
}
