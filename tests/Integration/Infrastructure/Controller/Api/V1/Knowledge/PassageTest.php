<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Controller\Api\V1\Knowledge;

use App\Tests\Integration\Infrastructure\Controller\Api\AbstractApiTestCase;
use App\Tests\Support\KnowledgeFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/** Lot L10 : consultation d'un passage cité. */
final class PassageTest extends AbstractApiTestCase
{
    use KnowledgeFixtures;

    private KernelBrowser $client;
    private string $token;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->token = $this->login($this->client)['token'];
    }

    public function testACitedPassageIsShownWithItsSourceItsVersionAndItsLocation(): void
    {
        $passage = $this->passagesOf($this->document('fiche-exemple.md', ['title' => 'Fiche différentiel']))[3];

        $shown = $this->apiRequest($this->client, 'GET', '/api/v1/passages/' . $passage->getUuid(), $this->token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([
            'uuid' => $passage->getUuid(),
            'corpus' => 'regulatory',
            'title' => 'Fiche différentiel',
            'publisher' => 'Hector',
            'kind' => 'hector_sheet',
            'version' => 'v1',
            'publishedOn' => '2026-11-01',
            'effectiveFrom' => '2026-12-01',
            'effectiveUntil' => null,
            'withdrawn' => false,
            'page' => null,
            'section' => 'Fiche d\'exemple : dispositif différentiel > Vérifications > Article 1 — Bouton de test',
            'article' => '1',
            'excerpt' => 'Le bouton de test vérifie le mécanisme de déclenchement.',
        ], $shown);
    }

    public function testKn5TheExcerptFollowsTheLicenseOfTheSource(): void
    {
        $limited = $this->passagesOf($this->document('fiche-exemple.md', ['excerptMax' => 20]))[3];
        $forbidden = $this->passagesOf($this->document('fiche-exemple.md', ['title' => 'Norme', 'excerptAllowed' => false]))[3];

        self::assertSame('Le bouton de test vé…', $this->apiRequest($this->client, 'GET', '/api/v1/passages/' . $limited->getUuid(), $this->token)['excerpt']);
        // Extrait interdit : un simple renvoi au document, avec sa localisation
        $reference = $this->apiRequest($this->client, 'GET', '/api/v1/passages/' . $forbidden->getUuid(), $this->token);
        self::assertNull($reference['excerpt']);
        self::assertSame('1', $reference['article']);
    }

    public function testKn4APassageOfAWithdrawnVersionCanStillBeRead(): void
    {
        $version = $this->document('fiche-exemple.md');
        $passage = $this->passagesOf($version)[0];
        $this->withdraw($version);

        $shown = $this->apiRequest($this->client, 'GET', '/api/v1/passages/' . $passage->getUuid(), $this->token);

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($shown['withdrawn']);
    }

    public function testADraftOrAnUnknownPassageDoesNotExistForAReader(): void
    {
        $draft = $this->passagesOf($this->document('fiche-exemple.md', ['publish' => false]))[0];

        $this->apiRequest($this->client, 'GET', '/api/v1/passages/' . $draft->getUuid(), $this->token);
        self::assertResponseStatusCodeSame(404);

        $unknown = $this->apiRequest($this->client, 'GET', '/api/v1/passages/0b507871-8b5e-4575-b297-a630310fc06e', $this->token, language: 'de');
        self::assertResponseStatusCodeSame(404);
        self::assertSame('Textstelle nicht gefunden.', $unknown['message']);
    }

    public function testReadingAPassageRequiresASession(): void
    {
        $this->client->request('GET', '/api/v1/passages/0b507871-8b5e-4575-b297-a630310fc06e');

        self::assertResponseStatusCodeSame(401);
    }
}
