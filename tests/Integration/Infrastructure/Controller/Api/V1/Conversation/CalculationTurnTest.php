<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Controller\Api\V1\Conversation;

use App\Application\Conversation\Service\AnswerEngine;
use App\Infrastructure\Persistence\Doctrine\Fixtures\UserFixture;
use App\Tests\Integration\Infrastructure\Controller\Api\AbstractApiTestCase;
use App\Tests\Support\FakeChatModel;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Lot L14 : calculer depuis la discussion, sans que le modèle touche aux nombres. Le faux
 * modèle joue le modèle : il appelle l'outil, puis rédige sa réponse.
 */
final class CalculationTurnTest extends AbstractApiTestCase
{
    private const array CURRENT = [
        'calculateur' => 'single_phase_current',
        'entrees' => [
            ['nom' => 'power', 'valeur' => 3, 'unite' => 'kW'],
            ['nom' => 'voltage', 'valeur' => 230, 'unite' => 'V'],
            ['nom' => 'powerFactor', 'valeur' => 0.9, 'unite' => ''],
        ],
    ];

    private const string QUESTION = 'Quel courant pour un moteur de 3 kW en 230 V, cos φ 0,9 ?';

    private KernelBrowser $client;
    private FakeChatModel $model;
    private string $token;
    private string $conversation;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $model = static::getContainer()->get(FakeChatModel::class);
        self::assertInstanceOf(FakeChatModel::class, $model);
        $this->model = $model;
        $this->token = $this->login($this->client)['token'];
        $this->conversation = $this->apiRequest($this->client, 'POST', '/api/v1/conversations', $this->token)['uuid'];
    }

    public function testACalculationAskedInTheConversationIsDoneRecordedAndShownByTheServer(): void
    {
        $this->model
            ->willCallTool('calculer', self::CURRENT)
            ->willReply(FakeChatModel::draft([
                ['statement', 'declared_fact', 'Vous indiquez un moteur de 3 kW alimenté en 230 V, avec un facteur de puissance de 0,9.'],
                ['statement', 'calculation_result', 'Courant absorbé par le moteur.', ['C1']],
                ['statement', 'recommendation', 'Faites confirmer le choix de la protection par un électricien.'],
            ]));

        $turn = $this->send(self::QUESTION);

        self::assertSame('completed', $turn['status']);
        // Le modèle se voit offrir l'outil et ses consignes, puis reçoit le calcul et sa référence
        self::assertContains('calculer', array_column($this->model->requests[0]->tools, 'name'));
        self::assertStringContainsString('Tu disposes de l\'outil `calculer`', $this->model->requests[0]->system);
        self::assertStringNotContainsString('{{', $this->model->requests[0]->system);
        $toolResult = $this->model->requests[1]->messages[2]->toolResults[0];
        self::assertFalse($toolResult->isError);
        self::assertStringStartsWith('[C1] Calcul exécuté.', $toolResult->content);

        $blocks = $turn['assistantMessage']['blocks'];
        self::assertSame(['statement', 'calculation', 'statement'], array_column($blocks, 'type'));
        $block = $blocks[1];
        self::assertSame(1, $block['version']);
        // Le modèle n'a écrit que la légende : entrées, hypothèses, étapes, résultat et version viennent du serveur
        self::assertSame('Courant absorbé par le moteur.', $block['text']);
        $calculation = $block['calculation'];
        self::assertSame(['key' => 'single_phase_current', 'version' => 1, 'title' => 'Courant absorbé en monophasé'], $calculation['calculator']);
        self::assertSame('I = P / (U × cos φ)', $calculation['formula']);
        self::assertSame('Loi physique : puissance active en régime sinusoïdal monophasé.', $calculation['source']);
        self::assertCount(3, $calculation['assumptions']);
        self::assertSame(['Puissance active', 'Tension', 'Facteur de puissance'], array_column($calculation['inputs'], 'label'));
        self::assertEquals([3, 230, 0.9], array_column($calculation['inputs'], 'value'));
        self::assertSame(['kW', 'V', ''], array_column($calculation['inputs'], 'unit'));
        self::assertSame(['S = P / cos φ', 'I = S / U'], array_column($calculation['steps'], 'expression'));
        self::assertEquals(14.5, $calculation['result']['value']);
        self::assertSame('A', $calculation['result']['unit']);
        self::assertEqualsWithDelta(14.4927536, $calculation['result']['exact'], 1e-6);
        self::assertStringStartsWith('Arrondi par excès', $calculation['result']['roundingRule']);
        // Une application qui ne connaît pas ce bloc lit le calcul en clair
        self::assertStringStartsWith("Courant absorbé par le moteur.\nCalcul : Courant absorbé en monophasé (version 1)", $block['fallbackText']);
        self::assertStringContainsString("Résultat : I = 14,5\u{00A0}A", $block['fallbackText']);
        // Un calcul n'est pas une source documentaire
        self::assertSame([], $turn['assistantMessage']['citations']);

        // Le nombre montré provient d'une exécution enregistrée, au nom de l'utilisateur, et relisible
        self::assertSame(
            [['owner_uuid' => UserFixture::USER_UUID, 'calculator_key' => 'single_phase_current', 'calculator_version' => 1]],
            $this->connection()->fetchAllAssociative('SELECT owner_uuid::text, calculator_key, calculator_version FROM calculation_run'),
        );
        $run = $this->apiRequest($this->client, 'GET', '/api/v1/calculations/' . $calculation['uuid'], $this->token);
        self::assertResponseStatusCodeSame(200);
        self::assertSame($calculation['result'], $run['result']);
        self::assertSame($calculation['inputs'], $run['inputs']);
        self::assertSame($calculation['createdAt'], $run['createdAt']);

        // La trace du tour dit quel calcul a été demandé, avec quelles valeurs
        $tools = json_decode((string) $this->connection()->fetchOne('SELECT trace_tools FROM conversation_turn WHERE uuid = ?', [$turn['uuid']]), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('calculer', $tools[0]['name']);
        self::assertSame('single_phase_current', $tools[0]['input']['calculateur']);
        self::assertFalse($tools[0]['error']);

        // Relue plus tard, la conversation montre le même calcul
        $messages = $this->apiRequest($this->client, 'GET', "/api/v1/conversations/{$this->conversation}/messages", $this->token);
        self::assertSame($blocks, $messages['items'][1]['blocks']);

        // Et au tour suivant, le modèle le retrouve dans l'historique
        $this->send('Et en triphasé ?');
        $history = array_column($this->model->requests[2]->messages, 'text');
        self::assertStringContainsString("Résultat : I = 14,5\u{00A0}A", $history[1]);
    }

    public function testWithAMissingValueNothingIsAssumedAndTheAssistantAsksForIt(): void
    {
        $incomplete = self::CURRENT;
        unset($incomplete['entrees'][2]);
        $this->model
            ->willCallTool('calculer', $incomplete)
            ->willReply(FakeChatModel::draft([
                ['statement', 'missing_information', 'Il me manque le facteur de puissance du moteur pour faire ce calcul.'],
                ['question', 'none', 'Quel est le facteur de puissance indiqué sur sa plaque ?'],
            ]));

        $turn = $this->send('Quel courant pour un moteur de 3 kW en 230 V ?');

        self::assertSame('completed', $turn['status']);
        // Le calculateur a refusé, et l'a dit au modèle
        $toolResult = $this->model->requests[1]->messages[2]->toolResults[0];
        self::assertTrue($toolResult->isError);
        self::assertStringContainsString('- Facteur de puissance (cos φ) : valeur manquante.', $toolResult->content);
        self::assertStringContainsString('Ne suppose aucune valeur', $toolResult->content);

        // L'utilisateur lit le motif tel que le calculateur le donne, puis la question
        $blocks = $turn['assistantMessage']['blocks'];
        self::assertSame(['statement', 'calculation_refusal', 'question'], array_column($blocks, 'type'));
        self::assertSame([
            'type' => 'calculation_refusal',
            'version' => 1,
            'calculator' => ['key' => 'single_phase_current', 'title' => 'Courant absorbé en monophasé'],
            'reasons' => ['Facteur de puissance (cos φ) : valeur manquante.'],
            'fallbackText' => "Calcul non effectué : Courant absorbé en monophasé\n- Facteur de puissance (cos φ) : valeur manquante.",
        ], $blocks[1]);
        // Aucun résultat, donc aucune exécution
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT count(*) FROM calculation_run'));
    }

    public function testARefusalIsGivenAsItIsInTheLanguageOfTheApplication(): void
    {
        $tooHigh = ['calculateur' => 'ohm_current', 'entrees' => [
            ['nom' => 'voltage', 'valeur' => 20, 'unite' => 'kV'],
            ['nom' => 'resistance', 'valeur' => 23, 'unite' => 'Ω'],
        ]];
        // Le modèle tente de minimiser le refus : ce qu'il écrit ne remplace pas le motif
        $this->model
            ->willCallTool('calculer', $tooHigh)
            ->willReply(FakeChatModel::statement('Diese Berechnung ist hier nicht möglich.', 'missing_information'));

        $turn = $this->send('Welcher Strom bei 20 kV und 23 Ω?', 'de');

        $blocks = $turn['assistantMessage']['blocks'];
        self::assertSame(['statement', 'calculation_refusal'], array_column($blocks, 'type'));
        self::assertSame('Strom durch einen Widerstand', $blocks[1]['calculator']['title']);
        self::assertSame(
            ['Spannung (U): Der Wert darf 1.000 V nicht überschreiten, die Grenze dieser Berechnung.'],
            array_map(static fn (string $reason): string => str_replace(["\u{202F}", "\u{00A0}"], ' ', $reason), $blocks[1]['reasons']),
        );
        self::assertStringStartsWith('Berechnung nicht durchgeführt: Strom durch einen Widerstand', $blocks[1]['fallbackText']);
        // Le modèle, lui, lit le motif en français, comme ses consignes
        self::assertStringContainsString('Tension (U) : la valeur ne doit pas dépasser', $this->model->requests[1]->messages[2]->toolResults[0]->content);
    }

    public function testTheCalculationIsShownInTheLanguageOfTheApplication(): void
    {
        $this->model
            ->willCallTool('calculer', self::CURRENT)
            ->willReply(FakeChatModel::draft([['statement', 'calculation_result', 'Current drawn by the motor.', ['C1']]]));

        $turn = $this->send('What current for a 3 kW motor at 230 V, power factor 0.9?', 'en');

        $block = $turn['assistantMessage']['blocks'][0];
        self::assertSame('calculation', $block['type']);
        self::assertSame('Single-phase current drawn', $block['calculation']['calculator']['title']);
        self::assertSame(['Active power', 'Voltage', 'Power factor'], array_column($block['calculation']['inputs'], 'label'));
        self::assertSame('Current drawn', $block['calculation']['result']['label']);
        self::assertStringContainsString("Result: I = 14.5\u{00A0}A", $block['fallbackText']);
        self::assertStringContainsString("Exact value: 14.4928\u{00A0}A", $block['fallbackText']);
        self::assertStringContainsString('Assumptions:', $block['fallbackText']);
    }

    public function testACalculationTheAnswerDoesNotPresentIsShownAnyway(): void
    {
        // Le modèle calcule puis donne le résultat de mémoire, sans le présenter : le serveur
        // montre le vrai calcul, avec les valeurs qui ont servi
        $this->model
            ->willCallTool('calculer', self::CURRENT)
            ->willReply(FakeChatModel::statement('Le courant est de l\'ordre de quinze ampères.'));

        $turn = $this->send(self::QUESTION);

        $blocks = $turn['assistantMessage']['blocks'];
        self::assertSame(['statement', 'calculation'], array_column($blocks, 'type'));
        self::assertEquals(14.5, $blocks[1]['calculation']['result']['value']);
        self::assertArrayNotHasKey('text', $blocks[1]);
    }

    public function testAResultTheModelWritesItselfNeverReachesTheUser(): void
    {
        // Ni calcul exécuté, ni même outil appelé : un « résultat de calcul » sorti du modèle
        $invented = FakeChatModel::draft([['statement', 'calculation_result', 'Le courant absorbé est de 13 A.']]);
        // Puis un vrai calcul, mais avec le chiffre recopié dans l'énoncé
        $this->model
            ->willReply($invented)
            ->willCallTool('calculer', self::CURRENT)
            ->willReply(FakeChatModel::draft([['statement', 'calculation_result', 'Le courant absorbé est de 14,5 A.', ['C1']]]));

        $turn = $this->send(self::QUESTION);

        self::assertSame('degraded', $turn['status']);
        self::assertSame([['type' => 'text', 'version' => 1, 'text' => $turn['assistantMessage']['blocks'][0]['text'], 'fallbackText' => AnswerEngine::DEGRADED_REPLY]], $turn['assistantMessage']['blocks']);
        $rejections = json_decode((string) $this->connection()->fetchOne('SELECT trace_rejections FROM conversation_turn WHERE uuid = ?', [$turn['uuid']]), true, flags: \JSON_THROW_ON_ERROR);
        self::assertStringContainsString('cite un calcul exécuté pendant ce tour', $rejections[0]['reasons'][0]);
        self::assertStringContainsString('ne contient aucun chiffre', $rejections[1]['reasons'][0]);
    }

    public function testAGuestCalculatesInTheConversationToo(): void
    {
        $guest = $this->openGuestAccount($this->client)['token'];
        $conversation = $this->apiRequest($this->client, 'POST', '/api/v1/conversations', $guest)['uuid'];
        $this->model
            ->willCallTool('calculer', self::CURRENT)
            ->willReply(FakeChatModel::draft([['statement', 'calculation_result', 'Courant absorbé par le moteur.', ['C1']]]));

        $turn = $this->apiRequest($this->client, 'POST', "/api/v1/conversations/$conversation/messages", $guest, ['text' => self::QUESTION]);

        self::assertSame('calculation', $turn['assistantMessage']['blocks'][0]['type']);
        $this->apiRequest($this->client, 'GET', '/api/v1/calculations/' . $turn['assistantMessage']['blocks'][0]['calculation']['uuid'], $guest);
        self::assertResponseStatusCodeSame(200);
        // Le calcul d'un invité n'est pas lisible par un autre compte
        $this->apiRequest($this->client, 'GET', '/api/v1/calculations/' . $turn['assistantMessage']['blocks'][0]['calculation']['uuid'], $this->token);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function send(string $text, ?string $language = null): array
    {
        $turn = $this->apiRequest($this->client, 'POST', "/api/v1/conversations/{$this->conversation}/messages", $this->token, ['text' => $text], $language);
        self::assertResponseStatusCodeSame(201);

        return $turn;
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
