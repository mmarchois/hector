<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Controller\Api\V1\Auth;

use App\Infrastructure\Persistence\Doctrine\Fixtures\UserFixture;
use App\Tests\Integration\Infrastructure\Controller\Api\AbstractApiTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/** Essai sans compte : lot L3, décision D17, règles ID1 à ID4. */
final class GuestTest extends AbstractApiTestCase
{
    public function testTheTermsOfUseArePublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/terms-of-use');

        self::assertResponseStatusCodeSame(200);
        $data = $this->json($client);
        self::assertSame($this->termsOfUseVersion(), $data['version']);
        self::assertStringContainsString('# Conditions d\'utilisation', $data['content']);
    }

    public function testAGuestAccountIsOpenedWithoutIdentity(): void
    {
        $client = static::createClient();
        $tokens = $this->openGuestAccount($client);

        self::assertNotEmpty($tokens['token']);
        self::assertNotEmpty($tokens['refresh_token']);

        $profile = $this->apiRequest($client, 'GET', '/api/v1/auth/me', $tokens['token']);
        self::assertResponseStatusCodeSame(200);
        self::assertTrue($profile['guest']);
        self::assertNull($profile['email']);
        self::assertNull($profile['fullName']);
        // ID3 : la qualification la plus restrictive
        self::assertFalse($profile['professional']);
        self::assertNull($profile['companyName']);
    }

    public function testTheTermsOfUseMustBeAccepted(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/auth/guest', []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            ['acceptedTermsOfUseVersion' => 'Acceptez les conditions d\'utilisation pour continuer.'],
            $this->violations($client),
        );
    }

    public function testOutdatedTermsOfUseAreAConflict(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/v1/auth/guest', ['acceptedTermsOfUseVersion' => '2025-01-01']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('terms_of_use_outdated', $this->json($client)['reason']);
    }

    public function testAGuestCannotLogInWithCredentials(): void
    {
        $client = static::createClient();
        $tokens = $this->openGuestAccount($client);
        $uuid = $this->apiRequest($client, 'GET', '/api/v1/auth/me', $tokens['token'])['uuid'];

        // ID1 : sans email ni mot de passe, rien ne permet de se connecter, pas même l'uuid
        $client->jsonRequest('POST', '/api/v1/auth/login', ['email' => $uuid, 'password' => 'nimporte-quoi']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testRegisteringConvertsTheGuestAndKeepsItsSession(): void
    {
        $client = static::createClient();
        $tokens = $this->openGuestAccount($client);
        $uuid = $this->apiRequest($client, 'GET', '/api/v1/auth/me', $tokens['token'])['uuid'];

        // Un invité a déjà accepté les conditions : il n'a pas à les renvoyer
        $profile = $this->apiRequest($client, 'POST', '/api/v1/auth/register', $tokens['token'], [
            'fullName' => 'Inès INVITÉE',
            'email' => 'ines@exemple.fr',
            'password' => 'motdepasse123',
        ]);

        // ID2 : même compte, donc même uuid et tout ce qui lui est rattaché
        self::assertResponseStatusCodeSame(201);
        self::assertSame($uuid, $profile['uuid']);
        self::assertFalse($profile['guest']);
        self::assertSame('ines@exemple.fr', $profile['email']);

        // La session en cours reste valide : jeton d'accès et jeton de rafraîchissement
        $me = $this->apiRequest($client, 'GET', '/api/v1/auth/me', $tokens['token']);
        self::assertResponseStatusCodeSame(200);
        self::assertFalse($me['guest']);

        $client->jsonRequest('POST', '/api/v1/auth/token/refresh', ['refresh_token' => $tokens['refresh_token']]);
        self::assertResponseStatusCodeSame(200);

        // Et le compte se connecte désormais par identifiants
        $login = $this->login($client, 'ines@exemple.fr', 'motdepasse123');
        self::assertSame($uuid, $this->apiRequest($client, 'GET', '/api/v1/auth/me', $login['token'])['uuid']);
    }

    public function testAGuestCannotSaveAProfile(): void
    {
        $client = static::createClient();
        $tokens = $this->openGuestAccount($client);

        $error = $this->apiRequest($client, 'PUT', '/api/v1/auth/me', $tokens['token'], [
            'fullName' => 'Inès INVITÉE',
            'email' => 'ines@exemple.fr',
            'professional' => false,
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('guest_account', $error['reason']);
        self::assertSame('Créez un compte pour enregistrer un profil.', $error['message']);
    }

    public function testGuestCreationIsLimitedByIpAddress(): void
    {
        $client = static::createClient();
        // Compteurs en mémoire : le kernel doit survivre entre les requêtes (limite de test : 3)
        $client->disableReboot();

        for ($i = 0; $i < 3; ++$i) {
            $this->openGuestAccount($client);
        }

        $client->jsonRequest('POST', '/api/v1/auth/guest', ['acceptedTermsOfUseVersion' => $this->termsOfUseVersion()]);

        self::assertResponseStatusCodeSame(429);
        self::assertSame(
            'Trop de comptes ouverts depuis cette connexion. Réessayez plus tard ou connectez-vous.',
            $this->json($client)['message'],
        );
    }

    public function testThePurgeDeletesOnlyTheGuestsWithoutSession(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $expired = $this->guestUuid($client);
        $alsoExpired = $this->guestUuid($client);
        $active = $this->guestUuid($client);

        $connection = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $connection->executeStatement(
            'UPDATE "user" SET registration_date = now() - interval \'40 days\' WHERE uuid IN (?, ?, ?)',
            [$expired, $alsoExpired, $active],
        );
        $connection->executeStatement(
            'UPDATE refresh_tokens SET valid = now() - interval \'1 day\' WHERE username IN (?, ?)',
            [$expired, $alsoExpired],
        );

        $tester = new CommandTester((new Application($client->getKernel()))->find('app:guests:purge'));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertSame("2 compte(s) invité(s) supprimé(s).\n", $tester->getDisplay());

        $remaining = $connection->fetchFirstColumn(
            'SELECT uuid::text FROM "user" WHERE uuid IN (?, ?, ?, ?)',
            [$expired, $alsoExpired, $active, UserFixture::INDIVIDUAL_UUID],
        );
        sort($remaining);
        $expected = [$active, UserFixture::INDIVIDUAL_UUID];
        sort($expected);
        // Un compte complet sans session n'est jamais purgé : il peut se reconnecter
        self::assertSame($expected, $remaining);
        self::assertSame(0, (int) $connection->fetchOne(
            'SELECT count(*) FROM refresh_tokens WHERE username IN (?, ?)',
            [$expired, $alsoExpired],
        ));
    }

    private function guestUuid(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): string
    {
        $tokens = $this->openGuestAccount($client);
        $uuid = $this->apiRequest($client, 'GET', '/api/v1/auth/me', $tokens['token'])['uuid'];
        self::assertIsString($uuid);

        return $uuid;
    }
}
