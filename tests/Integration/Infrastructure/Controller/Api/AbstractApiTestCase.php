<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Controller\Api;

use App\Infrastructure\Persistence\Doctrine\Fixtures\UserFixture;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractApiTestCase extends WebTestCase
{
    /**
     * Authentifie l'utilisateur via l'API.
     *
     * @return array{token: string, refresh_token: string}
     */
    protected function login(
        KernelBrowser $client,
        string $email = UserFixture::USER_EMAIL,
        string $password = UserFixture::USER_PASSWORD,
    ): array {
        $client->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        self::assertResponseStatusCodeSame(200);

        $data = $this->json($client);

        return ['token' => $data['token'], 'refresh_token' => $data['refresh_token']];
    }

    /** Version en vigueur des conditions d'utilisation (paramètre du conteneur, client déjà créé). */
    protected function termsOfUseVersion(): string
    {
        $version = static::getContainer()->getParameter('app.terms_of_use.version');
        self::assertIsString($version);

        return $version;
    }

    /**
     * Ouvre un compte invité, conditions d'utilisation acceptées.
     *
     * @return array{token: string, refresh_token: string}
     */
    protected function openGuestAccount(KernelBrowser $client): array
    {
        $client->jsonRequest('POST', '/api/v1/auth/guest', [
            'acceptedTermsOfUseVersion' => $this->termsOfUseVersion(),
        ]);
        self::assertResponseStatusCodeSame(201);

        $data = $this->json($client);

        return ['token' => $data['token'], 'refresh_token' => $data['refresh_token']];
    }

    /**
     * Requête authentifiée par Bearer token, corps JSON optionnel.
     *
     * @param array<string, mixed>|null $payload
     *
     * @return array<string, mixed> corps JSON décodé (vide si la réponse n'a pas de corps)
     */
    protected function apiRequest(KernelBrowser $client, string $method, string $uri, string $token, ?array $payload = null): array
    {
        $client->request($method, $uri, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], $payload !== null ? json_encode($payload, \JSON_THROW_ON_ERROR) : null);

        return $this->json($client);
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(KernelBrowser $client): array
    {
        $content = (string) $client->getResponse()->getContent();

        return $content === '' ? [] : json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * Champs en erreur d'une réponse 422.
     *
     * @return array<string, string> champ => message
     */
    protected function violations(KernelBrowser $client): array
    {
        return array_column($this->json($client)['violations'] ?? [], 'message', 'property');
    }
}
