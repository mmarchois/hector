<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Calculation\Capability;

use App\Application\Calculation\Capability\CalculateTool;
use App\Application\Calculation\Capability\CalculationBlocks;
use App\Application\Calculation\Capability\CalculationCapability;
use App\Application\Calculation\Command\RunCalculationCommand;
use App\Application\Calculation\Command\RunCalculationCommandHandler;
use App\Application\Calculation\Service\CalculationPresenter;
use App\Application\CommandBusInterface;
use App\Application\CommandInterface;
use App\Application\Conversation\Capability\Evidence;
use App\Application\Conversation\Port\Participant;
use App\Application\Conversation\Port\Prompt;
use App\Application\Conversation\Port\PromptCatalogInterface;
use App\Domain\Calculation\CalculationRun;
use App\Domain\Calculation\CalculatorCatalog;
use App\Domain\Calculation\Repository\CalculationRunRepositoryInterface;
use App\Domain\Port\DateUtilsInterface;
use App\Domain\Port\IdFactoryInterface;
use App\Tests\Support\FrenchCalculationTexts;
use PHPUnit\Framework\TestCase;

/**
 * Lot L14 : le modèle choisit le calcul et transmet les valeurs ; le serveur calcule,
 * enregistre et compose ce qui sera montré.
 */
final class CalculateToolTest extends TestCase
{
    private const array CURRENT = [
        'calculateur' => 'single_phase_current',
        'entrees' => [
            ['nom' => 'power', 'valeur' => 3, 'unite' => 'kW'],
            ['nom' => 'voltage', 'valeur' => 230, 'unite' => 'V'],
            ['nom' => 'powerFactor', 'valeur' => 0.9, 'unite' => ''],
        ],
    ];

    /** @var list<CalculationRun> */
    private array $recorded = [];

    /** @var list<CommandInterface> */
    private array $commands = [];

    public function testTheToolDescribesEveryCalculatorAndItsInputs(): void
    {
        $tool = $this->tool();

        self::assertSame('calculer', $tool->name());
        $description = $tool->description();
        self::assertStringContainsString('ne calcule jamais toi-même', $description);
        self::assertStringContainsString('Ne suppose aucune valeur', $description);
        self::assertStringContainsString('- ohm_voltage : Tension aux bornes d\'une résistance. Entrées : resistance (résistance, en Ω, mΩ, kΩ, MΩ) ; current (courant, en A, mA, kA).', $description);
        self::assertStringContainsString('- three_phase_current : Courant absorbé en triphasé. Entrées : power (puissance active, en W, kW) ; voltage (tension entre phases, en V, mV, kV) ; powerFactor (facteur de puissance, sans unité).', $description);

        $schema = $tool->inputSchema();
        self::assertSame(
            ['ohm_voltage', 'ohm_current', 'ohm_resistance', 'single_phase_power', 'three_phase_power', 'single_phase_current', 'three_phase_current'],
            $schema['properties']['calculateur']['enum'],
        );
        self::assertSame(['nom', 'valeur', 'unite'], $schema['properties']['entrees']['items']['required']);
    }

    public function testACalculationIsRecordedAndBecomesABlockTheServerComposed(): void
    {
        $evidence = new Evidence();

        $result = $this->tool()->call(self::CURRENT, $evidence);

        // Enregistré au nom de l'utilisateur, avec les valeurs telles qu'elles ont été données
        self::assertEquals([new RunCalculationCommand('owner', 'single_phase_current', [
            'power' => ['value' => 3, 'unit' => 'kW'],
            'voltage' => ['value' => 230, 'unit' => 'V'],
            'powerFactor' => ['value' => 0.9, 'unit' => ''],
        ])], $this->commands);
        self::assertCount(1, $this->recorded);

        // Le modèle reçoit une référence à citer, et le calcul pour pouvoir en parler
        self::assertFalse($result->error);
        self::assertStringStartsWith('[C1] Calcul exécuté.', $result->content);
        self::assertStringContainsString("Résultat : I = 14,5\u{00A0}A", $result->content);
        self::assertStringContainsString('énoncé `calculation_result` qui cite C1, sans aucun chiffre', $result->content);

        self::assertSame(['C1'], $evidence->exhibitReferences());
        $block = $evidence->exhibitOf('C1')?->toArray();
        self::assertIsArray($block);
        self::assertSame(CalculationBlocks::RESULT, $block['type']);
        self::assertSame(1, $block['version']);
        self::assertArrayNotHasKey('text', $block);
        $calculation = $block['calculation'];
        self::assertSame('run-uuid', $calculation['uuid']);
        self::assertSame('2026-11-25T10:00:00+00:00', $calculation['createdAt']);
        self::assertSame(['key' => 'single_phase_current', 'version' => 1, 'title' => 'Courant absorbé en monophasé'], $calculation['calculator']);
        self::assertSame('I = P / (U × cos φ)', $calculation['formula']);
        self::assertCount(3, $calculation['assumptions']);
        self::assertSame(['key' => 'power', 'label' => 'Puissance active', 'symbol' => 'P', 'value' => 3.0, 'unit' => 'kW'], $calculation['inputs'][0]);
        self::assertSame(['label' => 'Puissance apparente', 'expression' => 'S = P / cos φ', 'value' => 3333.0, 'unit' => 'VA'], $calculation['steps'][0]);
        self::assertSame(14.5, $calculation['result']['value']);
        self::assertSame('A', $calculation['result']['unit']);
        self::assertEqualsWithDelta(14.4927536, $calculation['result']['exact'], 1e-6);
    }

    public function testTheBlockReadsAsPlainTextInAnApplicationThatDoesNotKnowIt(): void
    {
        $evidence = new Evidence();
        $this->tool()->call(self::CURRENT, $evidence);

        self::assertSame(implode("\n", [
            'Calcul : Courant absorbé en monophasé (version 1)',
            'I = P / (U × cos φ)',
            "Puissance active (P) : 3\u{00A0}kW",
            "Tension (U) : 230\u{00A0}V",
            'Facteur de puissance (cos φ) : 0,9',
            "Puissance apparente : S = P / cos φ = 3\u{202F}333\u{00A0}VA",
            "Courant absorbé : I = S / U = 14,49\u{00A0}A",
            "Résultat : I = 14,5\u{00A0}A",
            "Valeur exacte : 14,4928\u{00A0}A",
            'Arrondi par excès, à quatre chiffres significatifs ; la partie entière n\'est jamais arrondie. Un courant à prévoir ne se sous-estime pas.',
            'Hypothèses :',
            '- Régime sinusoïdal établi ; tension et courant en valeurs efficaces.',
            '- Le facteur de puissance est celui que vous indiquez : aucune valeur n\'est supposée.',
            '- La puissance est la puissance active absorbée : ni rendement, ni coefficient d\'utilisation ou de simultanéité ne sont appliqués.',
            'Source : Loi physique : puissance active en régime sinusoïdal monophasé.',
        ]), $evidence->exhibitOf('C1')?->fallbackText);
    }

    public function testAnExactResultDoesNotRepeatItsValue(): void
    {
        $evidence = new Evidence();
        $this->tool()->call(['calculateur' => 'ohm_voltage', 'entrees' => [
            ['nom' => 'resistance', 'valeur' => 23, 'unite' => 'Ω'],
            ['nom' => 'current', 'valeur' => 10, 'unite' => 'A'],
        ]], $evidence);

        $text = (string) $evidence->exhibitOf('C1')?->fallbackText;
        self::assertStringContainsString("Résultat : U = 230\u{00A0}V", $text);
        self::assertStringNotContainsString('Valeur exacte', $text);
    }

    public function testAMissingValueIsRefusedAndTheReasonsWillBeShownAsTheyAre(): void
    {
        $evidence = new Evidence();
        $input = self::CURRENT;
        unset($input['entrees'][2]);

        $result = $this->tool()->call($input, $evidence);

        self::assertTrue($result->error);
        self::assertStringContainsString("Calcul refusé : rien n'a été calculé. Motifs :\n- Facteur de puissance (cos φ) : valeur manquante.", $result->content);
        self::assertStringContainsString('Ne suppose aucune valeur : demande ce qui manque.', $result->content);
        self::assertSame([], $this->recorded);
        self::assertSame([], $evidence->exhibitReferences());

        $notices = $evidence->unshown([]);
        self::assertCount(1, $notices);
        self::assertSame([
            'type' => CalculationBlocks::REFUSAL,
            'version' => 1,
            'calculator' => ['key' => 'single_phase_current', 'title' => 'Courant absorbé en monophasé'],
            'reasons' => ['Facteur de puissance (cos φ) : valeur manquante.'],
            'fallbackText' => "Calcul non effectué : Courant absorbé en monophasé\n- Facteur de puissance (cos φ) : valeur manquante.",
        ], $notices[0]->toArray());
    }

    public function testEveryReasonOfARefusalIsKept(): void
    {
        $evidence = new Evidence();

        $result = $this->tool()->call(['calculateur' => 'ohm_current', 'entrees' => [
            ['nom' => 'voltage', 'valeur' => 20, 'unite' => 'kV'],
            ['nom' => 'resistance', 'valeur' => 23, 'unite' => 'ohms'],
            ['nom' => 'frequence', 'valeur' => 50, 'unite' => 'Hz'],
        ]], $evidence);

        self::assertTrue($result->error);
        self::assertSame([
            'Entrée inconnue pour ce calcul : frequence.',
            "Tension (U) : la valeur ne doit pas dépasser 1\u{202F}000\u{00A0}V, limite de ce calcul.",
        ], $evidence->unshown([])[0]->toArray()['reasons']);
    }

    public function testACallTheModelGotWrongIsAnErrorForItNotANoticeForTheUser(): void
    {
        $evidence = new Evidence();
        $tool = $this->tool();

        $unknown = $tool->call(['calculateur' => 'voltage_drop', 'entrees' => []], $evidence);
        self::assertTrue($unknown->error);
        self::assertStringContainsString('Calculateur inconnu : « voltage_drop ». Calculateurs disponibles : ohm_voltage, ohm_current', $unknown->content);
        self::assertTrue($tool->call([], $evidence)->error);

        $twice = $tool->call(['calculateur' => 'ohm_voltage', 'entrees' => [
            ['nom' => 'current', 'valeur' => 10, 'unite' => 'A'],
            ['nom' => 'current', 'valeur' => 16, 'unite' => 'A'],
        ]], $evidence);
        self::assertSame('L\'entrée « current » est donnée deux fois.', $twice->content);

        $unnamed = $tool->call(['calculateur' => 'ohm_voltage', 'entrees' => [['valeur' => 10, 'unite' => 'A'], 'texte']], $evidence);
        self::assertSame('Chaque entrée a un nom, une valeur et une unité.', $unnamed->content);
        self::assertTrue($unnamed->error);

        self::assertSame([], $evidence->unshown([]));
        self::assertSame([], $this->commands);
    }

    public function testOutsideTheApplicationNothingIsRecorded(): void
    {
        $evidence = new Evidence();

        // Évaluation : pas de compte, donc pas d'exécution enregistrée, mais le même calcul
        $result = $this->tool(ownerUuid: null)->call(self::CURRENT, $evidence);

        self::assertFalse($result->error);
        self::assertSame([], $this->commands);
        $calculation = $evidence->exhibitOf('C1')?->toArray()['calculation'];
        self::assertNull($calculation['uuid']);
        self::assertNull($calculation['createdAt']);
        self::assertSame(14.5, $calculation['result']['value']);

        $refused = $this->tool(ownerUuid: null)->call(['calculateur' => 'ohm_voltage', 'entrees' => []], $evidence);
        self::assertTrue($refused->error);
    }

    public function testTheCapabilityOffersTheToolAndItsInstructionsToEveryone(): void
    {
        $prompts = $this->createStub(PromptCatalogInterface::class);
        $prompts->method('get')->willReturnCallback(static fn (string $name): Prompt => new Prompt($name, 'v1', 'Consignes de ' . $name));
        $presenter = new CalculationPresenter(new FrenchCalculationTexts());
        $capability = new CalculationCapability(new CalculatorCatalog(), $this->bus(), new CalculationBlocks($presenter, new FrenchCalculationTexts()), $presenter, $prompts);

        foreach ([new Participant(guest: true, professional: false), new Participant(guest: false, professional: true, uuid: 'owner', locale: 'de')] as $participant) {
            $tools = $capability->tools($participant);

            self::assertCount(1, $tools);
            self::assertInstanceOf(CalculateTool::class, $tools[0]);
            self::assertSame('Consignes de calculation', $capability->instructions($participant));
        }

        // L'outil construit pour un compte enregistre en son nom
        $capability->tools(new Participant(guest: false, professional: true, uuid: 'owner'))[0]->call(self::CURRENT, new Evidence());
        self::assertSame('owner', $this->recorded[0]->getOwnerUuid());
    }

    private function tool(?string $ownerUuid = 'owner'): CalculateTool
    {
        $presenter = new CalculationPresenter(new FrenchCalculationTexts());

        return new CalculateTool(new CalculatorCatalog(), $this->bus(), new CalculationBlocks($presenter, new FrenchCalculationTexts()), $presenter, $ownerUuid, 'fr');
    }

    /** Un bus qui passe la commande au vrai handler, sur un dépôt en mémoire. */
    private function bus(): CommandBusInterface
    {
        $repository = $this->createStub(CalculationRunRepositoryInterface::class);
        $repository->method('add')->willReturnCallback(function (CalculationRun $run): CalculationRun {
            $this->recorded[] = $run;

            return $run;
        });
        $idFactory = $this->createStub(IdFactoryInterface::class);
        $idFactory->method('make')->willReturn('run-uuid');
        $dateUtils = $this->createStub(DateUtilsInterface::class);
        $dateUtils->method('getNow')->willReturn(new \DateTimeImmutable('2026-11-25 10:00:00 UTC'));
        $handler = new RunCalculationCommandHandler(new CalculatorCatalog(), $repository, $idFactory, $dateUtils);

        $bus = $this->createStub(CommandBusInterface::class);
        $bus->method('handle')->willReturnCallback(function (CommandInterface $command) use ($handler): CalculationRun {
            $this->commands[] = $command;
            self::assertInstanceOf(RunCalculationCommand::class, $command);

            return $handler($command);
        });

        return $bus;
    }
}
