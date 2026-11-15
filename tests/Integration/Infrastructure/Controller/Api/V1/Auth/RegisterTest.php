<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Controller\Api\V1\Auth;

use App\Infrastructure\Persistence\Doctrine\Fixtures\UserFixture;
use App\Tests\Integration\Infrastructure\Controller\Api\AbstractApiTestCase;

final class RegisterTest extends AbstractApiTestCase
{
    public function testRegisterAProfessional(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/auth/register', [
            'fullName' => 'Marie OHM',
            'email' => 'Marie.Ohm@exemple.fr',
            'password' => 'motdepasse123',
            'professional' => true,
            'companyName' => 'Ohm Élec',
            'siret' => '98765432109876',
            'vatNumber' => 'FR98765432109',
        ]);

        self::assertResponseStatusCodeSame(201);
        $data = $this->json($client);
        self::assertNotEmpty($data['uuid']);
        self::assertSame('marie.ohm@exemple.fr', $data['email']);
        self::assertSame('Marie OHM', $data['fullName']);
        self::assertSame(['ROLE_USER'], $data['roles']);
        self::assertTrue($data['professional']);
        self::assertSame('Ohm Élec', $data['companyName']);
        self::assertSame('98765432109876', $data['siret']);
        self::assertSame('FR98765432109', $data['vatNumber']);
        self::assertArrayNotHasKey('password', $data);

        // Le compte est immédiatement utilisable
        $tokens = $this->login($client, 'marie.ohm@exemple.fr', 'motdepasse123');
        $profile = $this->apiRequest($client, 'GET', '/api/v1/auth/me', $tokens['token']);
        self::assertSame($data['uuid'], $profile['uuid']);
    }

    public function testRegisterAnIndividualWithoutCompany(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/auth/register', [
            'fullName' => 'Paul VOLT',
            'email' => 'paul.volt@exemple.fr',
            'password' => 'motdepasse123',
        ]);

        self::assertResponseStatusCodeSame(201);
        $data = $this->json($client);
        self::assertFalse($data['professional']);
        self::assertNull($data['companyName']);
        self::assertNull($data['siret']);
    }

    public function testAColleagueJoinsTheExistingCompanyWithTheSameSiret(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/auth/register', [
            'fullName' => 'Collègue AMPÈRE',
            'email' => 'collegue@exemple.fr',
            'password' => 'motdepasse123',
            'professional' => true,
            'companyName' => 'Nom saisi différemment',
            'siret' => UserFixture::COMPANY_SIRET,
            'vatNumber' => 'FR12345678901',
        ]);

        self::assertResponseStatusCodeSame(201);
        // L'entreprise existante est réutilisée telle quelle (pas de doublon sur le SIRET)
        self::assertSame('Élec Chantier', $this->json($client)['companyName']);
    }

    public function testAProfessionalMustProvideCompanyInformation(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/auth/register', [
            'fullName' => 'Paul VOLT',
            'email' => 'paul.volt@exemple.fr',
            'password' => 'motdepasse123',
            'professional' => true,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['companyName', 'siret', 'vatNumber'], array_keys($this->violations($client)));
    }

    public function testInvalidFieldsAreReportedOneByOne(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/auth/register', [
            'fullName' => '   ',
            'email' => 'pas-un-email',
            'password' => 'court',
            'professional' => true,
            'companyName' => 'Ohm Élec',
            'siret' => '123',
            'vatNumber' => 'DE123',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([
            'fullName' => 'Saisissez votre nom.',
            'email' => 'Cet email n\'est pas valide.',
            'password' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'siret' => 'Le SIRET doit contenir 14 chiffres.',
            'vatNumber' => 'Format attendu : FR suivi de 11 chiffres.',
        ], $this->violations($client));
    }

    public function testAnEmptyBodyIsRejectedWithViolations(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/auth/register', []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['fullName', 'email', 'password'], array_keys($this->violations($client)));
    }

    public function testAMistypedFieldIsAViolationNotACrash(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/auth/register', [
            'fullName' => 'Paul VOLT',
            'email' => 'paul.volt@exemple.fr',
            'password' => 'motdepasse123',
            'professional' => 'oui',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('professional', $this->violations($client));
    }

    public function testMalformedJsonIsABadRequest(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/v1/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], '{"fullName": ');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(400, $this->json($client)['code']);
    }

    public function testAnAlreadyUsedEmailIsAConflict(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/auth/register', [
            'fullName' => 'Doublon',
            // Même email à la casse près
            'email' => strtoupper(UserFixture::USER_EMAIL),
            'password' => 'motdepasse123',
        ]);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('Un compte existe déjà avec cet email.', $this->json($client)['message']);
    }
}
