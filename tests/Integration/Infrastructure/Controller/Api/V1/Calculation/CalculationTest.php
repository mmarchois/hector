<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Controller\Api\V1\Calculation;

use App\Tests\Integration\Infrastructure\Controller\Api\AbstractApiTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/** Lot L13 : lister les calculateurs, calculer, relire un calcul. */
final class CalculationTest extends AbstractApiTestCase
{
    private const array CURRENT = [
        'calculator' => 'single_phase_current',
        'inputs' => [
            'power' => ['value' => 3, 'unit' => 'kW'],
            'voltage' => ['value' => 230, 'unit' => 'V'],
            'powerFactor' => ['value' => 0.9],
        ],
    ];

    private const array ASSUMPTIONS = [
        'Régime sinusoïdal établi ; tension et courant en valeurs efficaces.',
        'Le facteur de puissance est celui que vous indiquez : aucune valeur n\'est supposée.',
        'La puissance est la puissance active absorbée : ni rendement, ni coefficient d\'utilisation ou de simultanéité ne sont appliqués.',
    ];

    private const string ROUNDED_UP = 'Arrondi par excès, à quatre chiffres significatifs ; la partie entière n\'est jamais arrondie. Un courant à prévoir ne se sous-estime pas.';

    private const string UNKNOWN = '0b507871-8b5e-4575-b297-a630310fc06e';

    private KernelBrowser $client;
    private string $token;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->token = $this->login($this->client)['token'];
    }

    public function testTheCalculatorsAreListedWithEverythingTheyAnnounce(): void
    {
        $list = $this->apiRequest($this->client, 'GET', '/api/v1/calculators', $this->token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            ['ohm_voltage', 'ohm_current', 'ohm_resistance', 'single_phase_power', 'three_phase_power', 'single_phase_current', 'three_phase_current'],
            array_column($list['items'], 'key'),
        );
        self::assertEquals([
            'key' => 'single_phase_current',
            'version' => 1,
            'title' => 'Courant absorbé en monophasé',
            'description' => 'Calcule le courant absorbé par un récepteur à partir de sa puissance active, de la tension et du facteur de puissance.',
            'inputs' => [
                ['key' => 'power', 'label' => 'Puissance active', 'symbol' => 'P', 'unit' => 'W', 'units' => ['W', 'kW'], 'above' => 0, 'max' => 10_000_000],
                ['key' => 'voltage', 'label' => 'Tension', 'symbol' => 'U', 'unit' => 'V', 'units' => ['V', 'mV', 'kV'], 'above' => 0, 'max' => 1000],
                // Un rapport n'a pas d'unité : sa seule « unité » acceptée est l'absence d'unité
                ['key' => 'powerFactor', 'label' => 'Facteur de puissance', 'symbol' => 'cos φ', 'unit' => '', 'units' => [''], 'above' => 0, 'max' => 1],
            ],
            'output' => ['key' => 'current', 'label' => 'Courant absorbé', 'symbol' => 'I', 'unit' => 'A', 'rounding' => 'up', 'roundingRule' => self::ROUNDED_UP, 'max' => 10_000],
            'assumptions' => self::ASSUMPTIONS,
            'formula' => 'I = P / (U × cos φ)',
            'source' => 'Loi physique : puissance active en régime sinusoïdal monophasé.',
        ], $list['items'][5]);
        self::assertSame(['Ω', 'mΩ', 'kΩ', 'MΩ'], $list['items'][0]['inputs'][0]['units']);
        self::assertSame('nearest', $list['items'][0]['output']['rounding']);
    }

    public function testTheCalculatorsAreDescribedInTheLanguageOfTheApplication(): void
    {
        $english = $this->apiRequest($this->client, 'GET', '/api/v1/calculators', $this->token, language: 'en')['items'][5];
        self::assertSame('Single-phase current drawn', $english['title']);
        self::assertSame('Active power', $english['inputs'][0]['label']);
        self::assertStringStartsWith('Rounded up, to four significant figures', $english['output']['roundingRule']);
        self::assertSame('The power factor is the one you give: no value is assumed.', $english['assumptions'][1]);
        // La formule s'écrit avec des symboles : elle ne se traduit pas
        self::assertSame('I = P / (U × cos φ)', $english['formula']);

        $german = $this->apiRequest($this->client, 'GET', '/api/v1/calculators', $this->token, language: 'de')['items'][5];
        self::assertSame('Aufgenommener Strom, einphasig', $german['title']);
        self::assertSame('Physikalisches Gesetz: Wirkleistung bei einphasigem sinusförmigem Wechselstrom.', $german['source']);
    }

    public function testACalculationIsMadeRecordedAndReadBackAsItWasMade(): void
    {
        $made = $this->apiRequest($this->client, 'POST', '/api/v1/calculations', $this->token, self::CURRENT);

        self::assertResponseStatusCodeSame(201);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $made['uuid']);
        // 3 000 / (230 × 0,9) = 14,4927… : la valeur exacte reste lisible à côté de l'arrondi
        self::assertEqualsWithDelta(14.492753623188406, $made['result']['exact'], 1e-12);
        self::assertEquals([
            'uuid' => $made['uuid'],
            'calculator' => ['key' => 'single_phase_current', 'version' => 1, 'title' => 'Courant absorbé en monophasé'],
            'formula' => 'I = P / (U × cos φ)',
            'source' => 'Loi physique : puissance active en régime sinusoïdal monophasé.',
            'assumptions' => self::ASSUMPTIONS,
            // Les entrées telles qu'elles ont été données, dans leur unité
            'inputs' => [
                ['key' => 'power', 'label' => 'Puissance active', 'symbol' => 'P', 'value' => 3, 'unit' => 'kW'],
                ['key' => 'voltage', 'label' => 'Tension', 'symbol' => 'U', 'value' => 230, 'unit' => 'V'],
                ['key' => 'powerFactor', 'label' => 'Facteur de puissance', 'symbol' => 'cos φ', 'value' => 0.9, 'unit' => ''],
            ],
            'steps' => [
                ['label' => 'Puissance apparente', 'expression' => 'S = P / cos φ', 'value' => 3333, 'unit' => 'VA'],
                ['label' => 'Courant absorbé', 'expression' => 'I = S / U', 'value' => 14.49, 'unit' => 'A'],
            ],
            'result' => [
                'key' => 'current',
                'label' => 'Courant absorbé',
                'symbol' => 'I',
                'value' => 14.5,
                'unit' => 'A',
                'exact' => $made['result']['exact'],
                'roundingRule' => self::ROUNDED_UP,
            ],
            'createdAt' => $made['createdAt'],
        ], $made);
        self::assertNotFalse(\DateTimeImmutable::createFromFormat(\DATE_ATOM, $made['createdAt']));
        self::assertSame(1, $this->recordedRuns());

        $read = $this->apiRequest($this->client, 'GET', '/api/v1/calculations/' . $made['uuid'], $this->token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame($made, $read);

        // Relu dans une autre langue : mêmes nombres, textes traduits
        $german = $this->apiRequest($this->client, 'GET', '/api/v1/calculations/' . $made['uuid'], $this->token, language: 'de');
        self::assertSame('Aufgenommener Strom', $german['result']['label']);
        self::assertSame('Scheinleistung', $german['steps'][0]['label']);
        self::assertSame($made['result']['value'], $german['result']['value']);
    }

    public function testTheSameCalculationGivesTheSameResult(): void
    {
        $first = $this->apiRequest($this->client, 'POST', '/api/v1/calculations', $this->token, self::CURRENT);
        $second = $this->apiRequest($this->client, 'POST', '/api/v1/calculations', $this->token, self::CURRENT);

        // CA1 : deux exécutions distinctes, un seul résultat
        self::assertNotSame($first['uuid'], $second['uuid']);
        self::assertSame($first['result'], $second['result']);
        self::assertSame($first['steps'], $second['steps']);
    }

    public function testARefusedCalculationSaysWhyForEachInputAndRecordsNothing(): void
    {
        $this->apiRequest($this->client, 'POST', '/api/v1/calculations', $this->token, [
            'calculator' => 'single_phase_current',
            'inputs' => ['power' => ['value' => 3, 'unit' => 'kW'], 'voltage' => ['value' => 230]],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([
            'inputs.voltage' => 'Tension (U) : indiquez l\'unité (V, mV, kV).',
            // Aucun facteur de puissance n'est supposé à la place de celui qui manque
            'inputs.powerFactor' => 'Facteur de puissance (cos φ) : valeur manquante.',
        ], $this->violations($this->client));
        self::assertSame(0, $this->recordedRuns());
    }

    public function testAResultOutOfTheCoveredDomainIsRefused(): void
    {
        $this->apiRequest($this->client, 'POST', '/api/v1/calculations', $this->token, [
            'calculator' => 'ohm_voltage',
            'inputs' => ['resistance' => ['value' => 1, 'unit' => 'MΩ'], 'current' => ['value' => 10, 'unit' => 'A']],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            ['inputs' => 'Le résultat dépasse 1 000 V, limite de ce calcul : vérifiez les valeurs saisies.'],
            $this->plainViolations(),
        );
        self::assertSame(0, $this->recordedRuns());
    }

    public function testARefusalIsWrittenInTheLanguageOfTheApplication(): void
    {
        $tooHigh = ['calculator' => 'ohm_current', 'inputs' => ['voltage' => ['value' => 20, 'unit' => 'kV'], 'resistance' => ['value' => 23, 'unit' => 'Ω']]];

        $this->apiRequest($this->client, 'POST', '/api/v1/calculations', $this->token, $tooHigh, 'en');
        self::assertSame(['inputs.voltage' => 'Voltage (U): the value must not exceed 1,000 V, the limit of this calculation.'], $this->plainViolations());

        $this->apiRequest($this->client, 'POST', '/api/v1/calculations', $this->token, $tooHigh, 'de');
        self::assertSame(['inputs.voltage' => 'Spannung (U): Der Wert darf 1.000 V nicht überschreiten, die Grenze dieser Berechnung.'], $this->plainViolations());

        $this->apiRequest($this->client, 'POST', '/api/v1/calculations', $this->token, ['calculator' => 'voltage_drop', 'inputs' => []], 'en');
        self::assertSame(['calculator' => 'Unknown calculator.'], $this->violations($this->client));
    }

    public function testAnUnknownCalculatorIsRefused(): void
    {
        $this->apiRequest($this->client, 'POST', '/api/v1/calculations', $this->token, ['calculator' => 'voltage_drop', 'inputs' => []]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['calculator' => 'Calculateur inconnu.'], $this->violations($this->client));
    }

    public function testTheRequestNamesACalculatorAndGivesItsInputs(): void
    {
        $this->apiRequest($this->client, 'POST', '/api/v1/calculations', $this->token, []);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            ['calculator' => 'Choisissez un calcul.', 'inputs' => 'Indiquez les valeurs du calcul.'],
            $this->violations($this->client),
        );

        // Des entrées qui ne sont pas un objet
        $this->apiRequest($this->client, 'POST', '/api/v1/calculations', $this->token, ['calculator' => 'ohm_voltage', 'inputs' => '23 ohms et 10 ampères']);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('inputs', $this->violations($this->client));
        self::assertSame(0, $this->recordedRuns());
    }

    public function testTheCalculationOfSomeoneElseDoesNotExist(): void
    {
        $uuid = $this->apiRequest($this->client, 'POST', '/api/v1/calculations', $this->token, self::CURRENT)['uuid'];
        $other = $this->openGuestAccount($this->client)['token'];

        $this->apiRequest($this->client, 'GET', '/api/v1/calculations/' . $uuid, $other);
        self::assertResponseStatusCodeSame(404);

        $unknown = $this->apiRequest($this->client, 'GET', '/api/v1/calculations/' . self::UNKNOWN, $this->token);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('Calcul introuvable.', $unknown['message']);

        $english = $this->apiRequest($this->client, 'GET', '/api/v1/calculations/' . self::UNKNOWN, $this->token, language: 'en');
        self::assertSame('Calculation not found.', $english['message']);
    }

    public function testAGuestCanCalculate(): void
    {
        $guest = $this->openGuestAccount($this->client)['token'];

        $made = $this->apiRequest($this->client, 'POST', '/api/v1/calculations', $guest, self::CURRENT);

        self::assertResponseStatusCodeSame(201);
        self::assertEquals(14.5, $made['result']['value']);
    }

    public function testCalculatingRequiresASession(): void
    {
        foreach ([['GET', '/api/v1/calculators'], ['POST', '/api/v1/calculations'], ['GET', '/api/v1/calculations/' . self::UNKNOWN]] as [$method, $uri]) {
            $this->client->request($method, $uri);

            self::assertResponseStatusCodeSame(401, $method . ' ' . $uri);
        }
    }

    public function testTheCalculationsOfAGuestFollowTheAccountTheyAreMergedInto(): void
    {
        $guest = $this->openGuestAccount($this->client);
        $uuid = $this->apiRequest($this->client, 'POST', '/api/v1/calculations', $guest['token'], self::CURRENT)['uuid'];

        $this->apiRequest($this->client, 'POST', '/api/v1/auth/merge-guest', $this->token, ['guestRefreshToken' => $guest['refresh_token']]);
        self::assertResponseStatusCodeSame(204);

        $read = $this->apiRequest($this->client, 'GET', '/api/v1/calculations/' . $uuid, $this->token);
        self::assertResponseStatusCodeSame(200);
        self::assertSame($uuid, $read['uuid']);
    }

    public function testDeletingAnAccountErasesItsCalculations(): void
    {
        $this->apiRequest($this->client, 'POST', '/api/v1/calculations', $this->token, self::CURRENT);
        self::assertSame(1, $this->recordedRuns());

        $this->apiRequest($this->client, 'DELETE', '/api/v1/auth/me', $this->token);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->recordedRuns());
    }

    public function testTooManyCalculationsInARowAreHeldBack(): void
    {
        $unknown = ['calculator' => 'voltage_drop', 'inputs' => []];
        for ($i = 0; $i < 60; ++$i) {
            $this->apiRequest($this->client, 'POST', '/api/v1/calculations', $this->token, $unknown);
            self::assertResponseStatusCodeSame(422);
        }

        $refused = $this->apiRequest($this->client, 'POST', '/api/v1/calculations', $this->token, self::CURRENT);

        self::assertResponseStatusCodeSame(429);
        self::assertSame('Vous lancez trop de calculs à la suite. Patientez un instant.', $refused['message']);
        self::assertSame(0, $this->recordedRuns());
    }

    private function recordedRuns(): int
    {
        $connection = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        return (int) $connection->fetchOne('SELECT count(*) FROM calculation_run');
    }

    /**
     * Violations, espaces insécables ramenées à des espaces simples : leur nature exacte
     * (fine ou non) dépend de la version des données de langue du serveur.
     *
     * @return array<string, string>
     */
    private function plainViolations(): array
    {
        return array_map(
            static fn (string $message): string => str_replace(["\u{202F}", "\u{00A0}"], ' ', $message),
            $this->violations($this->client),
        );
    }
}
