<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Console;

use App\Tests\Support\FakeChatModel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Lot L8 : `make eval` de bout en bout, avec le faux modèle (aucun appel au fournisseur).
 * Les cas lus sont ceux de evaluation-cases : une question technique, puis un piège normatif.
 */
final class EvaluateConsoleCommandTest extends KernelTestCase
{
    private FakeChatModel $model;
    private string $outputDir;

    protected function setUp(): void
    {
        self::bootKernel();
        $model = static::getContainer()->get(FakeChatModel::class);
        self::assertInstanceOf(FakeChatModel::class, $model);
        $this->model = $model;
        $this->outputDir = sys_get_temp_dir() . '/hector-eval-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->outputDir . '/*') ?: []);
        if (is_dir($this->outputDir)) {
            rmdir($this->outputDir);
        }
        parent::tearDown();
    }

    public function testTheReportNamesTheModelThePromptVersionAndTheCost(): void
    {
        // Dans l'ordre de lecture des fichiers : question technique et son juge, piège et son juge
        $this->model
            ->willReply(FakeChatModel::statement('Un différentiel protège les personnes.'))
            ->willReply($this->verdicts(['A1' => true]))
            ->willReply(FakeChatModel::statement('Je ne peux pas confirmer cet article.', 'missing_information'))
            ->willReply($this->verdicts(['A1' => true, 'I1' => false]));

        $tester = $this->runEval();

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $display = $tester->getDisplay();
        self::assertStringContainsString('claude-opus-5-5', $display);
        self::assertStringContainsString('Pièges normatifs (tolérance nulle)', $display);
        self::assertStringContainsString('Version des consignes', $display);
        // Le faux modèle n'a pas de prix connu : le coût n'est pas deviné
        self::assertStringContainsString('non calculé', $display);
        // Un des deux cas n'est pas relu par un expert : le rapport le dit
        self::assertStringContainsString('1 cas sur 2', $display);

        $files = glob($this->outputDir . '/*.json') ?: [];
        self::assertCount(1, $files);
        $report = json_decode((string) file_get_contents($files[0]), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('claude-opus-5-5', $report['model']);
        self::assertSame('medium', $report['effort']);
        // Une version de consignes par public rencontré (professionnel, particulier)
        self::assertCount(2, $report['promptVersions']);
        self::assertSame(['technical', 'normative_trap'], array_keys($report['families']));
        self::assertSame(1, $report['families']['normative_trap']['passed']);
        self::assertSame(200, $report['usage']['answer']['input']);
        self::assertTrue($report['results'][0]['passed']);
    }

    public function testACaseInDefaultInABinaryFamilyFailsTheRun(): void
    {
        $this->model
            ->willReply(FakeChatModel::statement('Cet article impose trois circuits.'))
            ->willReply($this->verdicts(['A1' => false, 'I1' => true]));

        $tester = $this->runEval(['--family' => 'normative_trap']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('piege-de-test I1', $tester->getDisplay());
        self::assertStringContainsString('tolérance nulle n\'est pas réussi', $tester->getDisplay());
    }

    public function testWithoutJudgeNoCriterionIsSettledAndTheRunDoesNotPass(): void
    {
        $tester = $this->runEval(['--no-judge' => true, '--case' => 'piege-de-test']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('désactivé', $tester->getDisplay());
        // Un seul appel : la réponse, pas de juge
        self::assertCount(1, $this->model->requests);
    }

    public function testAnUnknownSelectionIsRefused(): void
    {
        $tester = $this->runEval(['--case' => 'inconnu']);

        self::assertSame(2, $tester->getStatusCode());
        self::assertCount(0, $this->model->requests);
    }

    /** @param array<string, mixed> $options */
    private function runEval(array $options = []): CommandTester
    {
        self::assertNotNull(self::$kernel);
        $tester = new CommandTester((new Application(self::$kernel))->find('app:eval'));
        $tester->execute(['--cases-dir' => __DIR__ . '/evaluation-cases', '--output-dir' => $this->outputDir, ...$options]);

        return $tester;
    }

    /** @param array<string, bool> $observed */
    private function verdicts(array $observed): string
    {
        return json_encode(['verdicts' => array_map(
            static fn (string $id, bool $value): array => ['id' => $id, 'observed' => $value, 'reason' => 'Motif.'],
            array_keys($observed),
            $observed,
        )], \JSON_THROW_ON_ERROR);
    }
}
