<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Application\Conversation\Port\Participant;
use App\Application\Conversation\Port\Prompt;
use App\Application\Conversation\Port\PromptCatalogInterface;
use App\Domain\Conversation\Block;
use App\Domain\Conversation\Citation;
use PHPat\Selector\Selector;
use PHPat\Selector\SelectorInterface;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Règles de dépendance entre couches et entre contextes (docs/ARCHITECTURE.md, § 4),
 * vérifiées par PHPStan (phpat) à chaque `make check`.
 *
 * Un contexte est un dossier de src/Domain ou de src/Application : un nouveau contexte
 * est donc isolé par défaut, et ne peut utiliser un autre contexte que si le lien est
 * déclaré dans ALLOWED_LINKS.
 */
final class ArchitectureRules
{
    /**
     * Liens permis entre contextes (docs/DOMAIN.md, § 2) : contexte => contextes qu'il utilise.
     * Les contextes qui n'existent pas encore sont déclarés dès maintenant : la carte est
     * celle du document, et un lien non prévu reste refusé.
     */
    private const array ALLOWED_LINKS = [
        'User' => ['Company'],
        'Knowledge' => ['Regulation'],
        'Regulation' => [],
        'Calculation' => ['Regulation'],
        'Diagnosis' => ['Safety', 'Calculation'],
        'Safety' => ['User'],
        'Catalog' => ['Knowledge'],
        'ProductRecommendation' => ['Catalog', 'Calculation'],
        'Experience' => ['Diagnosis', 'Safety', 'Regulation'],
    ];

    /**
     * Ce qu'une capacité connaît du socle, en plus de App\Application\Conversation\Capability :
     * à qui elle parle, où lire ses consignes, la source qu'elle donne à citer et le bloc
     * qu'elle compose pour être montré.
     */
    private const array CAPABILITY_CONTRACT = [
        Participant::class,
        PromptCatalogInterface::class,
        Prompt::class,
        Citation::class,
        Block::class,
    ];

    /** Socle : il n'utilise aucun contexte, et les contextes ne s'y branchent que par ses capacités. */
    private const string CONVERSATION = 'Conversation';

    /** Espaces de noms transverses, qui ne sont pas des contextes (ports techniques communs). */
    private const array SHARED = ['Port'];

    private const string SRC = __DIR__ . '/../../src';

    #[TestRule]
    public function domainDependsOnlyOnDomain(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Domain'))
            ->canOnlyDependOn()
            ->classes(Selector::inNamespace('App\Domain'))
            ->because('Le domaine est du PHP pur : ni application, ni infrastructure, ni framework, ni bibliothèque.');
    }

    #[TestRule]
    public function applicationDependsOnlyOnApplicationAndDomain(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Application'))
            ->canOnlyDependOn()
            ->classes(Selector::inNamespace('App\Application'), Selector::inNamespace('App\Domain'))
            ->because('L\'application ne dépend que du domaine : ni infrastructure, ni framework, ni SDK de fournisseur.');
    }

    /**
     * Un contexte n'utilise que les contextes que la carte lui permet.
     *
     * @return iterable<string, Rule>
     */
    #[TestRule]
    public function contextsUseOnlyAllowedContexts(): iterable
    {
        $contexts = self::contexts();

        foreach ($contexts as $context) {
            $forbidden = self::CONVERSATION === $context
                ? array_diff($contexts, [$context])
                : array_diff($contexts, [$context, self::CONVERSATION], self::ALLOWED_LINKS[$context] ?? []);

            if ([] === $forbidden) {
                continue;
            }

            yield $context => PHPat::rule()
                ->classes(...self::layersOf($context))
                ->shouldNotDependOn()
                ->classes(...array_merge(...array_map(self::layersOf(...), array_values($forbidden))))
                ->because(\sprintf('Le contexte %s n\'utilise que %s (docs/DOMAIN.md, § 2).', $context, self::describe(self::ALLOWED_LINKS[$context] ?? [])));
        }
    }

    /**
     * Les contextes métier ne connaissent du socle que son contrat de capacité, et seulement
     * depuis leur propre dossier Capability (docs/ARCHITECTURE.md, § 6).
     *
     * @return iterable<string, Rule>
     */
    #[TestRule]
    public function contextsReachConversationOnlyThroughCapabilities(): iterable
    {
        $conversation = self::layersOf(self::CONVERSATION);
        $capabilityPort = Selector::inNamespace(\sprintf('App\Application\%s\Capability', self::CONVERSATION));

        foreach (array_diff(self::contexts(), [self::CONVERSATION]) as $context) {
            $capability = Selector::inNamespace(\sprintf('App\Application\%s\Capability', $context));

            yield $context . ':outside-capability' => PHPat::rule()
                ->classes(...self::layersOf($context))
                ->excluding($capability)
                ->shouldNotDependOn()
                ->classes(...$conversation)
                ->because(\sprintf('Seul App\Application\%s\Capability peut se brancher sur la conversation.', $context));

            yield $context . ':capability' => PHPat::rule()
                ->classes($capability)
                ->shouldNotDependOn()
                ->classes(...$conversation)
                ->excluding($capabilityPort, ...array_map(Selector::classname(...), self::CAPABILITY_CONTRACT))
                ->because('Une capacité ne connaît du socle que son contrat de capacité.');
        }
    }

    /** @return list<SelectorInterface> */
    private static function layersOf(string $context): array
    {
        return [
            Selector::inNamespace(\sprintf('App\Domain\%s', $context)),
            Selector::inNamespace(\sprintf('App\Application\%s', $context)),
        ];
    }

    /**
     * Contextes existants (dossiers de src/Domain et src/Application) et contextes prévus par la carte.
     *
     * @return list<string>
     */
    private static function contexts(): array
    {
        $directories = array_merge(
            glob(self::SRC . '/Domain/*', \GLOB_ONLYDIR) ?: [],
            glob(self::SRC . '/Application/*', \GLOB_ONLYDIR) ?: [],
        );

        $contexts = array_merge(
            array_map(basename(...), $directories),
            array_keys(self::ALLOWED_LINKS),
            array_merge(...array_values(self::ALLOWED_LINKS)),
            [self::CONVERSATION],
        );

        return array_values(array_diff(array_unique($contexts), self::SHARED));
    }

    /** @param list<string> $contexts */
    private static function describe(array $contexts): string
    {
        return [] === $contexts ? 'lui-même' : 'lui-même et ' . implode(', ', $contexts);
    }
}
