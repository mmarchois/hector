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

    /** Texte libre : message de l'utilisateur, ou réponse non structurée. */
    public static function text(string $text): self
    {
        return new self(self::TEXT, 1, ['text' => $text], $text);
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
}
