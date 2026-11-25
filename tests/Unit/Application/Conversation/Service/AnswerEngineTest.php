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

    /** @param list<ToolInterface>|null $tools */
    private function answer(?array $tools = null): TurnOutcome
    {
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
            $tools ?? [$search, $broken],
        ));
    }

    private function citation(string $id): Citation
    {
        return new Citation($id, 'regulatory', 'Fiche d\'exemple', 'Hector', 'v1', '2026-11-01', null, null, null, 'Vérifications', null, 'Extrait.');
    }
}
