<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Conversation\Service;

use App\Application\Conversation\Port\Participant;
use App\Application\Conversation\Port\ParticipantInterface;
use App\Application\Conversation\Service\FreeAllowancePolicy;
use App\Domain\Conversation\Exception\FreeAllowanceExhaustedException;
use App\Domain\Conversation\Repository\TurnRepositoryInterface;
use PHPUnit\Framework\TestCase;

/** CV8 et question P10 : allocation gratuite selon le type de compte. */
final class FreeAllowancePolicyTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-24 10:00:00');
    }

    public function testAGuestHasATotalAllowanceThenMustSignUp(): void
    {
        $this->policy(guest: true, total: 9)->assertAllowed('owner', $this->now);

        try {
            $this->policy(guest: true, total: 10)->assertAllowed('owner', $this->now);
            self::fail('Une FreeAllowanceExhaustedException était attendue.');
        } catch (FreeAllowanceExhaustedException $e) {
            self::assertTrue($e->signupRequired);
        }
    }

    public function testAnAccountHasAnAllowanceOverTheLast24Hours(): void
    {
        $this->policy(guest: false, lastDay: 29)->assertAllowed('owner', $this->now);

        try {
            $this->policy(guest: false, lastDay: 30)->assertAllowed('owner', $this->now);
            self::fail('Une FreeAllowanceExhaustedException était attendue.');
        } catch (FreeAllowanceExhaustedException $e) {
            self::assertFalse($e->signupRequired);
        }
    }

    private function policy(bool $guest, int $total = 0, int $lastDay = 0): FreeAllowancePolicy
    {
        $participants = $this->createStub(ParticipantInterface::class);
        $participants->method('profileOf')->willReturn(new Participant($guest, false));

        $turns = $this->createStub(TurnRepositoryInterface::class);
        $turns->method('countByOwner')->willReturnCallback(
            fn (string $owner, ?\DateTimeImmutable $since = null): int => $since === null
                ? $total
                : ($since == $this->now->modify('-24 hours') ? $lastDay : throw new \LogicException('Période inattendue')),
        );

        return new FreeAllowancePolicy($participants, $turns, guestMessages: 10, accountMessagesPerDay: 30);
    }
}
