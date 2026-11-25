<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Conversation\Service;

use App\Application\Conversation\Port\Participant;
use App\Application\Conversation\Port\ParticipantInterface;
use App\Application\Conversation\Service\FreeAllowancePolicy;
use App\Domain\Conversation\Exception\FreeAllowanceExhaustedException;
use App\Domain\Conversation\Repository\TurnRepositoryInterface;
use PHPUnit\Framework\TestCase;

/** CV8 et question P10 : allocation gratuite selon le type de compte, en messages et en jetons. */
final class FreeAllowancePolicyTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-24 10:00:00');
    }

    public function testAGuestHasATotalAllowanceThenMustSignUp(): void
    {
        $this->policy(guest: true, total: [9, 149_999])->assertAllowed('owner', $this->now);

        $this->assertRefused(signupRequired: true, policy: $this->policy(guest: true, total: [10, 0]));
    }

    public function testAGuestWhoseExchangesAreLongRunsOutOfTokensBeforeMessages(): void
    {
        $this->assertRefused(signupRequired: true, policy: $this->policy(guest: true, total: [2, 150_000]));
    }

    public function testAnAccountHasAnAllowanceOverTheLast24Hours(): void
    {
        $this->policy(guest: false, lastDay: [29, 499_999])->assertAllowed('owner', $this->now);

        $this->assertRefused(signupRequired: false, policy: $this->policy(guest: false, lastDay: [30, 0]));
        $this->assertRefused(signupRequired: false, policy: $this->policy(guest: false, lastDay: [3, 500_000]));
    }

    public function testAnAccountIsNotLimitedByItsOlderUsage(): void
    {
        $this->expectNotToPerformAssertions();
        // Seules les 24 dernières heures comptent pour un compte
        $this->policy(guest: false, total: [500, 9_000_000], lastDay: [0, 0])->assertAllowed('owner', $this->now);
    }

    private function assertRefused(bool $signupRequired, FreeAllowancePolicy $policy): void
    {
        try {
            $policy->assertAllowed('owner', $this->now);
            self::fail('Une FreeAllowanceExhaustedException était attendue.');
        } catch (FreeAllowanceExhaustedException $e) {
            self::assertSame($signupRequired, $e->signupRequired);
        }
    }

    /**
     * @param array{int, int} $total   messages et jetons depuis toujours
     * @param array{int, int} $lastDay messages et jetons des dernières 24 heures
     */
    private function policy(bool $guest, array $total = [0, 0], array $lastDay = [0, 0]): FreeAllowancePolicy
    {
        $participants = $this->createStub(ParticipantInterface::class);
        $participants->method('profileOf')->willReturn(new Participant($guest, false));

        $period = fn (?\DateTimeImmutable $since, int $index): int => $since === null
            ? $total[$index]
            : ($since == $this->now->modify('-24 hours') ? $lastDay[$index] : throw new \LogicException('Période inattendue'));
        $turns = $this->createStub(TurnRepositoryInterface::class);
        $turns->method('countByOwner')->willReturnCallback(static fn (string $owner, ?\DateTimeImmutable $since = null): int => $period($since, 0));
        $turns->method('sumTokensByOwner')->willReturnCallback(static fn (string $owner, ?\DateTimeImmutable $since = null): int => $period($since, 1));

        return new FreeAllowancePolicy($participants, $turns, guestMessages: 10, accountMessagesPerDay: 30, guestTokens: 150_000, accountTokensPerDay: 500_000);
    }
}
