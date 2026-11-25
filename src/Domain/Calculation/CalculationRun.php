<?php

declare(strict_types=1);

namespace App\Domain\Calculation;

/**
 * Exécution enregistrée d'un calcul : qui l'a demandé, quand, avec quel calculateur et quelle
 * version, et tout ce qui a été trouvé. Elle ne change plus une fois écrite : tout nombre
 * présenté comme un résultat de calcul en provient.
 */
class CalculationRun
{
    private string $calculatorKey;
    private int $calculatorVersion;
    /** @var array<string, mixed> */
    private array $trace;

    public function __construct(
        private string $uuid,
        private string $ownerUuid,
        CalculationResult $result,
        private \DateTimeImmutable $createdAt,
    ) {
        $this->calculatorKey = $result->definition->key;
        $this->calculatorVersion = $result->definition->version;
        $this->trace = $result->toArray();
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getOwnerUuid(): string
    {
        return $this->ownerUuid;
    }

    public function isOwnedBy(string $userUuid): bool
    {
        return $this->ownerUuid === $userUuid;
    }

    public function getCalculatorKey(): string
    {
        return $this->calculatorKey;
    }

    public function getCalculatorVersion(): int
    {
        return $this->calculatorVersion;
    }

    /** Le calcul tel qu'il a été fait : définition d'alors, entrées, étapes et résultat. */
    public function getResult(): CalculationResult
    {
        return CalculationResult::fromArray($this->trace);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
