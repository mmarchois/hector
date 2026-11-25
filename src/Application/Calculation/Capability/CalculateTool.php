<?php

declare(strict_types=1);

namespace App\Application\Calculation\Capability;

use App\Application\Calculation\Command\RunCalculationCommand;
use App\Application\Calculation\Service\CalculationPresenter;
use App\Application\CommandBusInterface;
use App\Application\Conversation\Capability\Evidence;
use App\Application\Conversation\Capability\ToolInterface;
use App\Application\Conversation\Capability\ToolResult;
use App\Domain\Calculation\CalculationEngine;
use App\Domain\Calculation\CalculationRun;
use App\Domain\Calculation\CalculatorCatalog;
use App\Domain\Calculation\CalculatorInterface;
use App\Domain\Calculation\Dimension;
use App\Domain\Calculation\Exception\CalculationRefusedException;
use App\Domain\Calculation\Exception\CalculatorNotFoundException;
use App\Domain\Calculation\InputDefinition;
use App\Domain\Calculation\Refusal;
use App\Domain\Calculation\Unit;

/**
 * Outil `calculer` : le modèle choisit un calculateur et transmet les valeurs données par
 * l'utilisateur ; le calcul est fait et enregistré par le serveur. Le modèle ne touche pas
 * aux nombres : un calcul réussi devient une pièce que le serveur montre lui-même, un calcul
 * refusé un avis montré tel quel.
 */
final readonly class CalculateTool implements ToolInterface
{
    public const string NAME = 'calculer';

    public function __construct(
        private CalculatorCatalog $catalog,
        private CommandBusInterface $commandBus,
        private CalculationBlocks $blocks,
        private CalculationPresenter $presenter,
        /** Compte au nom duquel le calcul est enregistré ; null hors de l'application : il n'est alors pas enregistré */
        private ?string $ownerUuid,
        /** Langue de ce qui est montré à l'utilisateur */
        private string $locale,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): string
    {
        $lines = [
            'Fait un calcul électrique exact et l\'enregistre. À appeler dès qu\'un résultat chiffré est demandé : ne calcule jamais toi-même.',
            'Ne transmets que des valeurs données par l\'utilisateur, chacune avec son unité. Ne suppose aucune valeur, même courante : le calculateur refuse en disant ce qui manque.',
            'Un calcul réussi rend une référence (C1, C2…) à citer dans un énoncé `calculation_result`. L\'application affiche alors elle-même les entrées, les hypothèses, les étapes, le résultat et la version du calculateur.',
            'Calculateurs, avec le nom de leurs entrées :',
        ];
        foreach ($this->catalog->all() as $calculator) {
            $definition = $calculator->definition();
            $lines[] = \sprintf(
                '- %s : %s. Entrées : %s.',
                $definition->key,
                $definition->title,
                implode(' ; ', array_map(self::describe(...), $definition->inputs)),
            );
        }

        return implode("\n", $lines);
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'calculateur' => [
                    'type' => 'string',
                    'enum' => array_map(static fn (CalculatorInterface $calculator): string => $calculator->definition()->key, $this->catalog->all()),
                ],
                'entrees' => [
                    'type' => 'array',
                    'description' => 'Les valeurs données par l\'utilisateur, et elles seules',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'nom' => ['type' => 'string', 'description' => 'Nom de l\'entrée, tel que dans la liste des calculateurs (voltage, current…)'],
                            'valeur' => ['type' => 'number'],
                            'unite' => ['type' => 'string', 'description' => 'Unité donnée par l\'utilisateur (V, kV, A, mA, Ω, kΩ, W, kW…) ; chaîne vide pour une grandeur sans unité'],
                        ],
                        'required' => ['nom', 'valeur', 'unite'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['calculateur', 'entrees'],
            'additionalProperties' => false,
        ];
    }

    public function call(array $input, Evidence $evidence): ToolResult
    {
        $key = \is_string($input['calculateur'] ?? null) ? trim($input['calculateur']) : '';
        try {
            $calculator = $this->catalog->get($key);
        } catch (CalculatorNotFoundException) {
            return new ToolResult(\sprintf(
                'Calculateur inconnu : « %s ». Calculateurs disponibles : %s. Si aucun ne convient, ce calcul ne se fait pas ici : dis-le.',
                $key,
                implode(', ', array_map(static fn (CalculatorInterface $known): string => $known->definition()->key, $this->catalog->all())),
            ), true);
        }

        $inputs = [];
        foreach (\is_array($input['entrees'] ?? null) ? $input['entrees'] : [] as $entry) {
            $name = \is_array($entry) && \is_string($entry['nom'] ?? null) ? trim($entry['nom']) : '';
            if ($name === '') {
                return new ToolResult('Chaque entrée a un nom, une valeur et une unité.', true);
            }
            if (isset($inputs[$name])) {
                return new ToolResult(\sprintf('L\'entrée « %s » est donnée deux fois.', $name), true);
            }
            $inputs[$name] = ['value' => $entry['valeur'] ?? null, 'unit' => $entry['unite'] ?? null];
        }

        try {
            if ($this->ownerUuid !== null) {
                /** @var CalculationRun $run */
                $run = $this->commandBus->handle(new RunCalculationCommand($this->ownerUuid, $key, $inputs));
                $block = $this->blocks->result($run->getResult(), $run->getUuid(), $run->getCreatedAt(), $this->locale);
                $result = $run->getResult();
            } else {
                $result = CalculationEngine::run($calculator, $inputs);
                $block = $this->blocks->result($result, null, null, $this->locale);
            }
        } catch (CalculationRefusedException $e) {
            // L'utilisateur lira les motifs tels que le calculateur les donne, pas une reformulation
            $evidence->notice($key, $this->blocks->refusal($calculator->definition(), $e->refusals, $this->locale));

            return new ToolResult(implode("\n", [
                'Calcul refusé : rien n\'a été calculé. Motifs :',
                ...array_map(static fn (Refusal $refusal): string => '- ' . $refusal->message(), $e->refusals),
                'L\'application montre ces motifs à l\'utilisateur tels quels : ne les reformule pas. Ne suppose aucune valeur : demande ce qui manque. Si l\'erreur vient de ton appel (nom d\'une entrée, écriture d\'une unité), corrige-le et rappelle l\'outil.',
            ]), true);
        }

        $reference = $evidence->exhibit($key, $block);

        return new ToolResult(implode("\n", [
            \sprintf('[%s] Calcul exécuté.', $reference),
            $this->presenter->plainText($result, 'fr'),
            \sprintf('Présente-le par un énoncé `calculation_result` qui cite %s, sans aucun chiffre dans son texte : l\'application affiche elle-même ce calcul en entier.', $reference),
        ]));
    }

    private static function describe(InputDefinition $input): string
    {
        return \sprintf(
            '%s (%s, %s)',
            $input->key,
            mb_strtolower($input->label),
            $input->dimension() === Dimension::Ratio
                ? 'sans unité'
                : 'en ' . implode(', ', array_map(static fn (Unit $unit): string => $unit->value, Unit::of($input->dimension()))),
        );
    }
}
