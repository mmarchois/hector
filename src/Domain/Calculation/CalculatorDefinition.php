<?php

declare(strict_types=1);

namespace App\Domain\Calculation;

/**
 * Ce qu'un calculateur annonce de lui-même (docs/DOMAIN.md, § 4.4) : ses entrées et sa sortie
 * typées, ses hypothèses, sa formule et la source de celle-ci (CA7). Changer la formule, une
 * constante, une borne ou l'arrondi, c'est changer la version (CA5).
 */
final readonly class CalculatorDefinition
{
    /**
     * @param list<InputDefinition> $inputs
     * @param list<string>          $assumptions hypothèses du calcul, toutes dites (CA4)
     */
    public function __construct(
        public string $key,
        public int $version,
        public string $title,
        public string $description,
        public array $inputs,
        public OutputDefinition $output,
        public array $assumptions,
        /** Formule, écrite avec les symboles des entrées */
        public string $formula,
        /** Loi physique ou référence du registre normatif (CA7) */
        public string $source,
    ) {
    }

    public function input(string $key): ?InputDefinition
    {
        foreach ($this->inputs as $input) {
            if ($input->key === $key) {
                return $input;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'version' => $this->version,
            'title' => $this->title,
            'description' => $this->description,
            'inputs' => array_map(static fn (InputDefinition $input): array => $input->toArray(), $this->inputs),
            'output' => $this->output->toArray(),
            'assumptions' => $this->assumptions,
            'formula' => $this->formula,
            'source' => $this->source,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['key'],
            (int) $data['version'],
            $data['title'],
            $data['description'],
            array_map(InputDefinition::fromArray(...), array_values($data['inputs'])),
            OutputDefinition::fromArray($data['output']),
            array_values($data['assumptions']),
            $data['formula'],
            $data['source'],
        );
    }
}
