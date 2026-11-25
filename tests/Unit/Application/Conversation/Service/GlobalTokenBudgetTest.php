<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Conversation\Service;

use App\Application\Conversation\Service\GlobalTokenBudget;
use App\Domain\Conversation\Exception\ServiceSaturatedException;
use App\Domain\Conversation\Repository\TurnRepositoryInterface;
use PHPUnit\Framework\TestCase;

/** CV9 : budget global de jetons sur 24 heures glissantes. */
final class GlobalTokenBudgetTest extends TestCase
{
    public function testTurnsAreRefusedOnceTheBudgetOfTheLast24HoursIsReached(): void
    {
        $now = new \DateTimeImmutable('2026-11-24 10:00:00');

        $this->budget(999_999, $now)->assertAvailable($now);

        $this->expectException(ServiceSaturatedException::class);
        $this->budget(1_000_000, $now)->assertAvailable($now);
    }

    private function budget(int $consumed, \DateTimeImmutable $now): GlobalTokenBudget
    {
        $turns = $this->createStub(TurnRepositoryInterface::class);
        $turns->method('sumTokens')->willReturnCallback(
            static fn (\DateTimeImmutable $since): int => $since == $now->modify('-24 hours') ? $consumed : throw new \LogicException('Période inattendue'),
        );

        return new GlobalTokenBudget($turns, tokensPerDay: 1_000_000);
    }
}
