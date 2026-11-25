<?php

declare(strict_types=1);

namespace App\Infrastructure\Evaluation;

/**
 * Prix des modèles, lus dans resources/evaluation/pricing.json avec leur source et leur date.
 * Un modèle absent du fichier n'a pas de coût calculé : on ne devine jamais un prix.
 */
final readonly class Pricing
{
    /**
     * @param array<string, array{input: float, output: float, cacheRead: float, cacheWrite: float}> $models
     *                                                                                                       prix par million de jetons
     */
    public function __construct(
        public string $currency,
        public string $source,
        private array $models,
    ) {
    }

    public static function fromFile(string $path): self
    {
        $data = json_decode((string) file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR);

        $models = [];
        foreach ($data['models'] as $model => $prices) {
            $models[$model] = [
                'input' => (float) $prices['input'],
                'output' => (float) $prices['output'],
                'cacheRead' => (float) $prices['cacheRead'],
                'cacheWrite' => (float) $prices['cacheWrite'],
            ];
        }

        return new self($data['currency'], $data['source'], $models);
    }

    /**
     * @param array{input: int, output: int, cacheRead: int, cacheCreation: int} $usage
     *
     * @return float|null null si le prix du modèle n'est pas connu
     */
    public function cost(?string $model, array $usage): ?float
    {
        if (array_sum($usage) === 0) {
            return 0.0;
        }

        $prices = null;
        $matched = '';
        foreach ($this->models as $name => $candidate) {
            // Le fournisseur peut renvoyer un identifiant daté (claude-opus-5-5-20261001) :
            // on retient le nom connu le plus long qui le commence
            if ($model !== null && str_starts_with($model, $name) && \strlen($name) > \strlen($matched)) {
                $prices = $candidate;
                $matched = $name;
            }
        }
        if ($prices === null) {
            return null;
        }

        return ($usage['input'] * $prices['input']
            + $usage['output'] * $prices['output']
            + $usage['cacheRead'] * $prices['cacheRead']
            + $usage['cacheCreation'] * $prices['cacheWrite']) / 1_000_000;
    }
}
