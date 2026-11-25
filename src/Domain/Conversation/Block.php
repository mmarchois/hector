<?php

declare(strict_types=1);

namespace App\Domain\Conversation;

/**
 * Élément typé et versionné d'un message (décision D12). Tout bloc porte un texte de
 * repli lisible, affiché par une version de l'application qui ne connaît pas son type (CV5).
 */
final readonly class Block
{
    public const string TEXT = 'text';
    public const string STATEMENT = 'statement';
    public const string QUESTION = 'question';
    public const string WARNING = 'warning';

    /** Intitulé par défaut d'une mise en garde dans son texte de repli. */
    public const string WARNING_LABEL = 'Attention';

    /**
     * @param array<string, mixed> $content champs propres au type
     */
    private function __construct(
        public string $type,
        public int $version,
        public array $content,
        public string $fallbackText,
    ) {
    }

    /** Texte libre : message de l'utilisateur, ou réponse fixe du serveur. */
    public static function text(string $text): self
    {
        return new self(self::TEXT, 1, ['text' => $text], $text);
    }

    /**
     * Énoncé de l'assistant, porteur d'une nature.
     *
     * @param list<string> $citations identifiants des passages ou exécutions de calcul cités
     * @param string|null  $source    source nommée d'un avis rapporté
     * @param string|null  $label     intitulé de la nature dans la langue de l'utilisateur ;
     *                                par défaut, l'intitulé français
     */
    public static function statement(StatementKind $kind, string $text, array $citations = [], ?string $source = null, ?string $label = null): self
    {
        $content = ['kind' => $kind->value, 'text' => $text, 'citations' => $citations];
        if ($source !== null && $source !== '') {
            $content['source'] = $source;
        }

        return new self(self::STATEMENT, 1, $content, \sprintf('%s : %s', $label ?? $kind->label(), $text));
    }

    /** Question posée à l'utilisateur. */
    public static function question(string $text): self
    {
        return new self(self::QUESTION, 1, ['text' => $text], $text);
    }

    /** Mise en garde, affichée en évidence. */
    public static function warning(string $text, ?string $label = null): self
    {
        return new self(self::WARNING, 1, ['text' => $text], \sprintf('%s : %s', $label ?? self::WARNING_LABEL, $text));
    }

    /**
     * @param array{type: string, version: int, fallbackText: string, ...} $data
     */
    public static function fromArray(array $data): self
    {
        $content = $data;
        unset($content['type'], $content['version'], $content['fallbackText']);

        return new self($data['type'], $data['version'], $content, $data['fallbackText']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'version' => $this->version, ...$this->content, 'fallbackText' => $this->fallbackText];
    }

    /** Texte propre du bloc, ou son texte de repli. */
    public function textContent(): string
    {
        $text = $this->content['text'] ?? null;

        return \is_string($text) ? $text : $this->fallbackText;
    }

    public function kind(): ?StatementKind
    {
        $kind = $this->content['kind'] ?? null;

        return \is_string($kind) ? StatementKind::tryFrom($kind) : null;
    }

    /** @return list<string> */
    public function citations(): array
    {
        $citations = $this->content['citations'] ?? [];

        return \is_array($citations) ? array_values(array_filter($citations, \is_string(...))) : [];
    }

    public function source(): ?string
    {
        $source = $this->content['source'] ?? null;

        return \is_string($source) ? $source : null;
    }
}
