<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Conversation\Service;

use App\Application\Conversation\Capability\Evidence;
use App\Application\Conversation\Capability\ToolInterface;
use App\Application\Conversation\Capability\ToolResult;
use App\Application\Conversation\Port\ChatMessage;
use App\Application\Conversation\Port\ChatRequest;
use App\Application\Conversation\Port\ChatRole;
use App\Application\Conversation\Service\AnswerEngine;
use App\Application\Conversation\Service\PreparedTurn;
use App\Application\Conversation\Service\TurnOutcome;
use App\Domain\Conversation\Block;
use App\Domain\Conversation\Citation;
use App\Tests\Support\FakeChatModel;
use App\Tests\Support\FrenchAnswerTexts;
use PHPUnit\Framework\TestCase;

/** Lot L10 : boucle d'outils du moteur et contrôle des citations (décision D7). */
final class AnswerEngineTest extends TestCase
{
    private FakeChatModel $model;

    protected function setUp(): void
    {
        $this->model = new FakeChatModel();
    }

    public function testAToolIsCalledThenTheAnswerCitesWhatItRead(): void
    {
        $this->model
            ->willCallTool('chercher', ['question' => 'bouton de test'])
            ->willReply(FakeChatModel::draft([
                ['statement', 'sourced_fact', 'Le bouton de test vérifie le mécanisme de déclenchement.', ['S1']],
                ['statement', 'recommendation', 'Appuyez dessus une fois par mois.'],
            ]));

        $outcome = $this->answer();

        self::assertFalse($outcome->degraded);
        // Le message enregistré cite par identifiant durable, pas par référence de tour
        self::assertSame(['passage-1'], $outcome->blocks[0]->citations());
        self::assertSame([], $outcome->blocks[1]->citations());
        self::assertEquals([$this->citation('passage-1')], $outcome->citations);
        self::assertCount(2, $outcome->evidence);
        self::assertSame([['name' => 'chercher', 'input' => ['question' => 'bouton de test'], 'read' => ['passage-1', 'passage-2'], 'error' => false]], $outcome->trace->tools);
        self::assertSame(200, $outcome->trace->inputTokens);

        // Le modèle se voit offrir l'outil, puis reçoit son résultat à la suite de son propre message
        self::assertSame('chercher', $this->model->requests[0]->tools[0]->name);
        $followUp = $this->model->requests[1]->messages;
        self::assertSame(ChatRole::Assistant, $followUp[1]->role);
        self::assertNotNull($followUp[1]->providerContent);
        self::assertSame('[S1] Premier passage. [S2] Second passage.', $followUp[2]->toolResults[0]->content);
        self::assertSame($followUp[1]->toolCalls[0]->id, $followUp[2]->toolResults[0]->toolCallId);
    }

    public function testCitingSomethingThatWasNotReadIsRejectedThenRetried(): void
    {
        $this->model
            ->willCallTool('chercher', ['question' => 'bouton de test'])
            ->willReply(FakeChatModel::draft([['statement', 'sourced_fact', 'Fait appuyé sur une source jamais lue.', ['S9']]]))
            ->willReply(FakeChatModel::draft([['statement', 'sourced_fact', 'Fait appuyé sur le second passage.', ['S2']]]));

        $outcome = $this->answer();

        self::assertFalse($outcome->degraded);
        self::assertSame(['passage-2'], $outcome->blocks[0]->citations());
        self::assertStringContainsString('cite S9', $outcome->trace->rejections[0]['reasons'][0] ?? '');
        // La reprise garde l'échange d'outils : le modèle n'a pas à chercher de nouveau
        self::assertCount(3, $this->model->requests);
        self::assertCount(5, $this->model->requests[2]->messages);
    }

    public function testWithoutAnyToolNothingCanBeCited(): void
    {
        $this->model
            ->willReply(FakeChatModel::draft([['statement', 'sourced_fact', 'Fait sans source lue.', ['S1']]]))
            ->willReply(FakeChatModel::draft([['statement', 'sourced_fact', 'Toujours sans source.', ['S1']]]));

        $outcome = $this->answer(tools: []);

        self::assertTrue($outcome->degraded);
        self::assertSame(AnswerEngine::DEGRADED_REPLY, $outcome->blocks[0]->textContent());
        self::assertSame([], $this->model->requests[0]->tools);
    }

    public function testAnUnknownOrFailingToolGivesTheModelAnErrorAndTheTurnGoesOn(): void
    {
        $this->model
            ->willCallTool('outil_inconnu', [])
            ->willCallTool('casse', [])
            ->willReply(FakeChatModel::statement('Je réponds sans outil.'));

        $outcome = $this->answer();

        self::assertFalse($outcome->degraded);
        self::assertSame([true, true], array_column($outcome->trace->tools ?? [], 'error'));
        self::assertTrue($this->model->requests[1]->messages[2]->toolResults[0]->isError);
        self::assertStringContainsString('Outil inconnu', $this->model->requests[1]->messages[2]->toolResults[0]->content);
        self::assertStringContainsString('L\'outil a échoué', $this->model->requests[2]->messages[4]->toolResults[0]->content);
    }

    public function testTheToolLoopIsBoundedAndEndsWithADegradedAnswer(): void
    {
        for ($i = 0; $i <= AnswerEngine::MAX_TOOL_ROUNDS; ++$i) {
            $this->model->willCallTool('chercher', ['question' => "essai $i"]);
        }

        $outcome = $this->answer();

        self::assertTrue($outcome->degraded);
        self::assertSame(AnswerEngine::DEGRADED_REPLY, $outcome->blocks[0]->textContent());
        self::assertCount(AnswerEngine::MAX_TOOL_ROUNDS + 1, $this->model->requests);
        self::assertCount(AnswerEngine::MAX_TOOL_ROUNDS, $outcome->trace->tools ?? []);
    }

    public function testASourceReadTwiceKeepsItsReference(): void
    {
        $evidence = new Evidence();

        self::assertSame('S1', $evidence->register($this->citation('passage-1')));
        self::assertSame('S2', $evidence->register($this->citation('passage-2')));
        self::assertSame('S1', $evidence->register($this->citation('passage-1')));
        self::assertSame(['S1', 'S2'], $evidence->references());
        self::assertNull($evidence->get('S3'));
    }

    public function testACalculationResultGivesWayToTheBlockTheServerComposed(): void
    {
        $this->model
            ->willCallTool('calcule', [])
            ->willReply(FakeChatModel::draft([
                ['statement', 'declared_fact', 'Vous indiquez 23 Ω et 10 A.'],
                ['statement', 'calculation_result', 'Tension aux bornes de la résistance.', ['C1']],
                ['statement', 'recommendation', 'Vérifiez la mesure avec un appareil adapté.'],
            ]));

        $outcome = $this->answer();

        self::assertFalse($outcome->degraded);
        self::assertSame(['statement', 'calculation', 'statement'], array_column($outcome->blocks, 'type'));
        // Les nombres sont ceux du serveur ; le modèle n'a fourni que la légende
        $block = $outcome->blocks[1]->toArray();
        self::assertSame(['result' => 230], $block['calculation']);
        self::assertSame('Tension aux bornes de la résistance.', $block['text']);
        self::assertSame("Tension aux bornes de la résistance.\nRésultat : U = 230 V", $block['fallbackText']);
        // Un calcul n'est pas une source documentaire
        self::assertSame([], $outcome->citations);
        self::assertSame('[C1] Calcul exécuté.', $this->model->requests[1]->messages[2]->toolResults[0]->content);
    }

    public function testACalculationTheAnswerDidNotPresentIsShownAnyway(): void
    {
        $this->model
            ->willCallTool('calcule', [])
            ->willReply(FakeChatModel::draft([
                ['statement', 'general_explanation', 'La tension se déduit de la résistance et du courant.'],
                ['question', 'none', 'Voulez-vous un autre calcul ?'],
            ]));

        $outcome = $this->answer();

        // Montré d'office, sans légende, avant la question posée à l'utilisateur
        self::assertSame(['statement', 'calculation', 'question'], array_column($outcome->blocks, 'type'));
        self::assertSame('Résultat : U = 230 V', $outcome->blocks[1]->fallbackText);
        self::assertArrayNotHasKey('text', $outcome->blocks[1]->toArray());
    }

    public function testARefusedCalculationIsShownAsItIs(): void
    {
        $this->model
            ->willCallTool('calcule', ['refuse' => true])
            ->willReply(FakeChatModel::draft([
                ['statement', 'missing_information', 'Il me manque le courant pour faire ce calcul.'],
                ['question', 'none', 'Quel courant traverse la résistance ?'],
            ]));

        $outcome = $this->answer();

        self::assertFalse($outcome->degraded);
        self::assertSame(['statement', 'calculation_refusal', 'question'], array_column($outcome->blocks, 'type'));
        self::assertSame(['Courant (I) : valeur manquante.'], $outcome->blocks[1]->toArray()['reasons']);
        self::assertTrue($outcome->trace->tools[0]['error']);
    }

    public function testARefusalFollowedByTheSameCalculationDoneIsNoLongerShown(): void
    {
        $this->model
            ->willCallTool('calcule', ['refuse' => true])
            ->willCallTool('calcule', [])
            ->willReply(FakeChatModel::draft([['statement', 'calculation_result', 'Tension aux bornes de la résistance.', ['C1']]]));

        $outcome = $this->answer();

        self::assertSame(['calculation'], array_column($outcome->blocks, 'type'));
    }

    public function testTheSameCalculationIsShownOnlyOnce(): void
    {
        $this->model
            ->willCallTool('calcule', [])
            ->willReply(FakeChatModel::draft([
                ['statement', 'calculation_result', 'Tension aux bornes de la résistance.', ['C1']],
                ['statement', 'calculation_result', 'Rappel du même calcul.', ['C1']],
            ]));

        self::assertSame(['calculation'], array_column($this->answer()->blocks, 'type'));
    }

    public function testAResultWrittenByTheModelNeverReachesTheUser(): void
    {
        // Un chiffre dans l'énoncé, puis un calcul qui n'a pas eu lieu : deux brouillons rejetés
        $this->model
            ->willCallTool('calcule', [])
            ->willReply(FakeChatModel::draft([['statement', 'calculation_result', 'La tension est de 230 V.', ['C1']]]))
            ->willReply(FakeChatModel::draft([['statement', 'calculation_result', 'Tension aux bornes de la résistance.', ['C2']]]));

        $outcome = $this->answer();

        self::assertTrue($outcome->degraded);
        self::assertSame(AnswerEngine::DEGRADED_REPLY, $outcome->blocks[0]->fallbackText);
        self::assertCount(1, $outcome->blocks);
        self::assertStringContainsString('ne contient aucun chiffre', $outcome->trace->rejections[0]['reasons'][0]);
        self::assertStringContainsString('cite C2', $outcome->trace->rejections[1]['reasons'][0]);
    }

    public function testWhatTheServerComposesIsKeptInOrderAndANoticeIsReplacedByTheNextOne(): void
    {
        $evidence = new Evidence();
        $block = static fn (string $text): Block => Block::composed('calculation', [], $text);

        $evidence->notice('ohm', $block('refus 1'));
        $evidence->notice('ohm', $block('refus 2'));
        $evidence->notice('puissance', $block('refus puissance'));
        self::assertSame([], $evidence->exhibitReferences());
        self::assertSame(['refus 2', 'refus puissance'], array_column($evidence->unshown([]), 'fallbackText'));

        // Un calcul qui aboutit efface le refus resté sur le même sujet, pas celui d'un autre
        self::assertSame('C1', $evidence->exhibit('ohm', $block('calcul 1')));
        self::assertSame('C2', $evidence->exhibit('ohm', $block('calcul 2')));
        $evidence->notice('ohm', $block('refus 3'));
        self::assertSame(['C1', 'C2'], $evidence->exhibitReferences());
        self::assertSame('calcul 2', $evidence->exhibitOf('C2')?->fallbackText);
        self::assertNull($evidence->exhibitOf('C3'));
        self::assertNull($evidence->exhibitOf('S1'));
        self::assertSame(['refus puissance', 'calcul 1', 'calcul 2', 'refus 3'], array_column($evidence->unshown([]), 'fallbackText'));
        self::assertSame(['refus puissance', 'calcul 2', 'refus 3'], array_column($evidence->unshown(['C1']), 'fallbackText'));
        // Les sources lues gardent leur propre numérotation
        self::assertSame('S1', $evidence->register($this->citation('passage-1')));
        self::assertSame(['S1'], $evidence->references());
    }

    /** @param list<ToolInterface>|null $tools */
    private function answer(?array $tools = null): TurnOutcome
    {
        $calculate = new class implements ToolInterface {
            public function name(): string
            {
                return 'calcule';
            }

            public function description(): string
            {
                return 'Calcule.';
            }

            public function inputSchema(): array
            {
                return ['type' => 'object'];
            }

            public function call(array $input, Evidence $evidence): ToolResult
            {
                if (($input['refuse'] ?? false) === true) {
                    $evidence->notice('ohm', Block::composed('calculation_refusal', ['reasons' => ['Courant (I) : valeur manquante.']], "Calcul non effectué\n- Courant (I) : valeur manquante."));

                    return new ToolResult('Calcul refusé.', true);
                }

                return new ToolResult(\sprintf('[%s] Calcul exécuté.', $evidence->exhibit('ohm', Block::composed('calculation', ['calculation' => ['result' => 230]], 'Résultat : U = 230 V'))));
            }
        };
        $search = new class($this->citation('passage-1'), $this->citation('passage-2')) implements ToolInterface {
            public function __construct(private readonly Citation $first, private readonly Citation $second)
            {
            }

            public function name(): string
            {
                return 'chercher';
            }

            public function description(): string
            {
                return 'Cherche.';
            }

            public function inputSchema(): array
            {
                return ['type' => 'object'];
            }

            public function call(array $input, Evidence $evidence): ToolResult
            {
                return new ToolResult(\sprintf('[%s] Premier passage. [%s] Second passage.', $evidence->register($this->first), $evidence->register($this->second)));
            }
        };
        $broken = new class implements ToolInterface {
            public function name(): string
            {
                return 'casse';
            }

            public function description(): string
            {
                return 'Échoue.';
            }

            public function inputSchema(): array
            {
                return ['type' => 'object'];
            }

            public function call(array $input, Evidence $evidence): ToolResult
            {
                throw new \RuntimeException('Base indisponible.');
            }
        };

        return (new AnswerEngine($this->model, new FrenchAnswerTexts()))->run(new PreparedTurn(
            new ChatRequest('Consignes', [new ChatMessage(ChatRole::User, 'À quoi sert le bouton de test ?')]),
            'abc123def456',
            'fr',
            $tools ?? [$search, $broken, $calculate],
        ));
    }

    private function citation(string $id): Citation
    {
        return new Citation($id, 'regulatory', 'Fiche d\'exemple', 'Hector', 'v1', '2026-11-01', null, null, null, 'Vérifications', null, 'Extrait.');
    }
}
