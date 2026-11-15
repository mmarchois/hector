<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Controller\Api\V1\Auth;

use App\Infrastructure\Persistence\Doctrine\Fixtures\UserFixture;
use App\Tests\Integration\Infrastructure\Controller\Api\AbstractApiTestCase;

final class ProfileTest extends AbstractApiTestCase
{
    private const array PROFILE = [
        'fullName' => 'Jean ÉLECTRICIEN',
        'email' => UserFixture::USER_EMAIL,
        'professional' => true,
        'companyName' => 'Élec Chantier',
        'siret' => UserFixture::COMPANY_SIRET,
        'vatNumber' => 'FR12345678901',
    ];

    public function testUpdateRequiresAToken(): void
    {
        $client = static::createClient();
        $client->jsonRequest('PUT', '/api/v1/auth/me', self::PROFILE);

        self::assertResponseStatusCodeSame(401);
    }

    public function testUpdateTheProfile(): void
    {
        $client = static::createClient();
        $tokens = $this->login($client);

        $profile = $this->apiRequest($client, 'PUT', '/api/v1/auth/me', $tokens['token'], [
            'fullName' => 'Jean VOLTA',
            'email' => UserFixture::USER_EMAIL,
            'professional' => true,
            'companyName' => 'Volta Élec',
            'siret' => UserFixture::COMPANY_SIRET,
            'vatNumber' => 'FR10987654321',
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('Jean VOLTA', $profile['fullName']);
        self::assertSame('Volta Élec', $profile['companyName']);
        self::assertSame('FR10987654321', $profile['vatNumber']);

        // Les modifications sont bien enregistrées
        $reloaded = $this->apiRequest($client, 'GET', '/api/v1/auth/me', $tokens['token']);
        self::assertSame($profile, $reloaded);
    }

    public function testChangingTheEmailKeepsTheSessionAlive(): void
    {
        $client = static::createClient();
        $tokens = $this->login($client);

        $this->apiRequest($client, 'PUT', '/api/v1/auth/me', $tokens['token'], [
            ...self::PROFILE,
            'email' => 'nouveau@hector.test',
        ]);
        self::assertResponseStatusCodeSame(200);

        // Les tokens identifient l'utilisateur par son uuid : ils survivent au changement d'email
        $profile = $this->apiRequest($client, 'GET', '/api/v1/auth/me', $tokens['token']);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('nouveau@hector.test', $profile['email']);

        $client->jsonRequest('POST', '/api/v1/auth/token/refresh', ['refresh_token' => $tokens['refresh_token']]);
        self::assertResponseStatusCodeSame(200);

        // La connexion se fait désormais avec le nouvel email
        $this->login($client, 'nouveau@hector.test');
    }

    public function testTheEmailOfAnotherAccountIsAConflict(): void
    {
        $client = static::createClient();
        $tokens = $this->login($client);

        $this->apiRequest($client, 'PUT', '/api/v1/auth/me', $tokens['token'], [
            ...self::PROFILE,
            'email' => UserFixture::INDIVIDUAL_EMAIL,
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testBecomingAnIndividualRemovesTheCompany(): void
    {
        $client = static::createClient();
        $tokens = $this->login($client);

        $profile = $this->apiRequest($client, 'PUT', '/api/v1/auth/me', $tokens['token'], [
            'fullName' => 'Jean ÉLECTRICIEN',
            'email' => UserFixture::USER_EMAIL,
            'professional' => false,
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertFalse($profile['professional']);
        self::assertNull($profile['companyName']);
    }

    public function testAnIndividualBecomingProfessionalMustProvideCompanyInformation(): void
    {
        $client = static::createClient();
        $tokens = $this->login($client, UserFixture::INDIVIDUAL_EMAIL);

        $this->apiRequest($client, 'PUT', '/api/v1/auth/me', $tokens['token'], [
            'fullName' => 'Paul PARTICULIER',
            'email' => UserFixture::INDIVIDUAL_EMAIL,
            'professional' => true,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['companyName', 'siret', 'vatNumber'], array_keys($this->violations($client)));
    }

    public function testChangeThePassword(): void
    {
        $client = static::createClient();
        $tokens = $this->login($client);

        $this->apiRequest($client, 'PUT', '/api/v1/auth/me', $tokens['token'], [
            ...self::PROFILE,
            'currentPassword' => UserFixture::USER_PASSWORD,
            'newPassword' => 'nouveau-mot-de-passe',
        ]);
        self::assertResponseStatusCodeSame(200);

        // Les sessions ouvertes avec l'ancien mot de passe sont révoquées
        $client->jsonRequest('POST', '/api/v1/auth/token/refresh', ['refresh_token' => $tokens['refresh_token']]);
        self::assertResponseStatusCodeSame(401);

        $client->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => UserFixture::USER_EMAIL,
            'password' => UserFixture::USER_PASSWORD,
        ]);
        self::assertResponseStatusCodeSame(401);

        $this->login($client, UserFixture::USER_EMAIL, 'nouveau-mot-de-passe');
    }

    public function testAWrongCurrentPasswordIsAViolation(): void
    {
        $client = static::createClient();
        $tokens = $this->login($client);

        $this->apiRequest($client, 'PUT', '/api/v1/auth/me', $tokens['token'], [
            ...self::PROFILE,
            'fullName' => 'Ne doit pas être enregistré',
            'currentPassword' => 'mauvais-mot-de-passe',
            'newPassword' => 'nouveau-mot-de-passe',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['currentPassword' => 'Mot de passe actuel incorrect.'], $this->violations($client));

        // Rien n'a été enregistré et l'ancien mot de passe fonctionne toujours
        $profile = $this->apiRequest($client, 'GET', '/api/v1/auth/me', $tokens['token']);
        self::assertSame('Jean ÉLECTRICIEN', $profile['fullName']);
        $this->login($client);
    }

    public function testATooShortNewPasswordIsAViolation(): void
    {
        $client = static::createClient();
        $tokens = $this->login($client);

        $this->apiRequest($client, 'PUT', '/api/v1/auth/me', $tokens['token'], [
            ...self::PROFILE,
            'currentPassword' => UserFixture::USER_PASSWORD,
            'newPassword' => 'court',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['newPassword'], array_keys($this->violations($client)));
    }

    public function testDeleteTheAccount(): void
    {
        $client = static::createClient();
        $tokens = $this->login($client);

        $this->apiRequest($client, 'DELETE', '/api/v1/auth/me', $tokens['token']);
        self::assertResponseStatusCodeSame(204);

        // Plus aucun accès : ni avec le token en cours, ni par refresh, ni par connexion
        $this->apiRequest($client, 'GET', '/api/v1/auth/me', $tokens['token']);
        self::assertResponseStatusCodeSame(401);

        $client->jsonRequest('POST', '/api/v1/auth/token/refresh', ['refresh_token' => $tokens['refresh_token']]);
        self::assertResponseStatusCodeSame(401);

        $client->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => UserFixture::USER_EMAIL,
            'password' => UserFixture::USER_PASSWORD,
        ]);
        self::assertResponseStatusCodeSame(401);
    }
}
