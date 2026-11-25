<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Adapter;

use App\Domain\Calculation\CalculationEngine;
use App\Domain\Calculation\CalculatorCatalog;
use App\Domain\Calculation\Exception\CalculationRefusedException;
use App\Domain\Calculation\Refusal;
use App\Domain\Calculation\Rounding;
use App\Infrastructure\Adapter\CalculationTexts;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\TranslatorBagInterface;

/** Décision D19 : un calcul se lit dans la langue de l'application, nombres compris. */
final class CalculationTextsTest extends KernelTestCase
{
    /** De quoi provoquer chaque motif de refus du moteur de calcul. */
    private const array REFUSED = [
        ['ohm_voltage', ['resistance' => ['value' => 23, 'unit' => 'Ω'], 'current' => ['value' => 10, 'unit' => 'A'], 'frequency' => 50]],
        ['ohm_voltage', []],
        ['ohm_voltage', ['resistance' => ['value' => '23', 'unit' => 'Ω'], 'current' => ['value' => 10]]],
        ['ohm_voltage', ['resistance' => ['value' => 23, 'unit' => 'V'], 'current' => ['value' => 0, 'unit' => 'A']]],
        ['ohm_voltage', ['resistance' => ['value' => 23, 'unit' => 'Ω'], 'current' => ['value' => 20, 'unit' => 'kA']]],
        ['ohm_voltage', ['resistance' => ['value' => 1, 'unit' => 'MΩ'], 'current' => ['value' => 10, 'unit' => 'A']]],
        ['ohm_current', ['voltage' => ['value' => 1, 'unit' => 'mV'], 'resistance' => ['value' => 1000, 'unit' => 'MΩ']]],
        ['single_phase_power', ['voltage' => ['value' => 230, 'unit' => 'V'], 'current' => ['value' => 16, 'unit' => 'A'], 'powerFactor' => ['value' => 90, 'unit' => '%']]],
    ];

    public function testEveryTextOfACalculationIsTranslatedIntoEveryOpenLanguage(): void
    {
        $texts = [];
        foreach ((new CalculatorCatalog())->all() as $calculator) {
            $definition = $calculator->definition();
            $texts = [...$texts, $definition->title, $definition->description, $definition->source, $definition->output->label, ...$definition->assumptions];
            $values = [];
            foreach ($definition->inputs as $input) {
                $texts[] = $input->label;
                $values[$input->key] = 1.0;
            }
            foreach ($calculator->compute($values)->steps as $step) {
                $texts[] = $step->label;
            }
        }
        foreach (Rounding::cases() as $rounding) {
            $texts[] = $rounding->rule();
        }

        $templates = [];
        foreach (self::REFUSED as [$key, $inputs]) {
            try {
                CalculationEngine::run((new CalculatorCatalog())->get($key), $inputs);
                self::fail(\sprintf('Le calcul %s aurait dû être refusé.', $key));
            } catch (CalculationRefusedException $e) {
                foreach ($e->refusals as $refusal) {
                    $templates[] = $refusal->template;
                }
            }
        }
        // Les dix motifs que le moteur sait donner
        self::assertCount(10, array_unique($templates));

        $translator = self::getContainer()->get('translator');
        self::assertInstanceOf(TranslatorBagInterface::class, $translator);
        $locales = self::getContainer()->getParameter('kernel.enabled_locales');
        self::assertIsArray($locales);

        foreach (array_diff($locales, ['fr']) as $locale) {
            $catalogue = $translator->getCatalogue($locale);
            foreach (array_unique([...$texts, ...$templates, 'Calcul introuvable.', 'Calculateur inconnu.', 'Vous lancez trop de calculs à la suite. Patientez un instant.']) as $text) {
                self::assertTrue($catalogue->defines($text), \sprintf('Traduction manquante en « %s » : %s', $locale, $text));
            }
        }
    }

    public function testARefusalIsWrittenWithTheWordsAndTheNumbersOfTheLanguage(): void
    {
        $texts = self::getContainer()->get(CalculationTexts::class);
        self::assertInstanceOf(CalculationTexts::class, $texts);
        $refusal = new Refusal(
            'voltage',
            '%input% (%symbol%) : la valeur ne doit pas dépasser %bound%%unit%, limite de ce calcul.',
            ['%symbol%' => 'U', '%bound%' => 1000.0, '%unit%' => "\u{00A0}V"],
            'Tension',
        );
        $plain = static fn (string $text): string => str_replace(["\u{202F}", "\u{00A0}"], ' ', $text);

        self::assertSame('Tension (U) : la valeur ne doit pas dépasser 1 000 V, limite de ce calcul.', $plain($texts->refusal($refusal, 'fr')));
        self::assertSame('Voltage (U): the value must not exceed 1,000 V, the limit of this calculation.', $plain($texts->refusal($refusal, 'en')));
        self::assertSame('Spannung (U): Der Wert darf 1.000 V nicht überschreiten, die Grenze dieser Berechnung.', $plain($texts->refusal($refusal, 'de')));
        // Le texte français du domaine et celui de l'adaptateur disent la même chose
        self::assertSame($plain($refusal->message()), $plain($texts->refusal($refusal, 'fr')));

        // Un refus sans grandeur en cause, et sans nombre
        $unknown = new Refusal('frequency', 'Entrée inconnue pour ce calcul : %key%.', ['%key%' => 'frequency']);
        self::assertSame('Unknown input for this calculation: frequency.', $texts->refusal($unknown, 'en'));
    }

    public function testANumberKeepsAllItsDecimals(): void
    {
        $texts = self::getContainer()->get(CalculationTexts::class);
        self::assertInstanceOf(CalculationTexts::class, $texts);

        self::assertSame('0,0005', $texts->number(0.0005, 'fr'));
        self::assertSame('0.9', $texts->number(0.9, 'en'));
        self::assertSame('1.234,5', $texts->number(1234.5, 'de'));
        self::assertSame('1,000,000,000', $texts->number(1_000_000_000.0, 'en'));
    }
}
