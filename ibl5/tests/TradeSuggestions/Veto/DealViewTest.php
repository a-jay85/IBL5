<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Veto;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Veto\Fixtures\VetoDealFactory;
use TradeSuggestions\Engine\DealScorer;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Veto\DealView;

final class DealViewTest extends TestCase
{
    public function testReceivedPickWorthMatchesSideGainPicksComponent(): void
    {
        $fixture = VetoDealFactory::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [],
            [],
            [],
            [],
            [],
            [21, VetoDealFactory::PARTNER_PICK_ID],
        );
        $scored = DealScorer::score($fixture['snapshot'], $fixture['deal']->candidate, 'fixture');
        $user = $fixture['deal']->user;

        self::assertCount(2, $user->receivedPicks);
        self::assertGreaterThan(0.0, $user->receivedPickWorth());
        self::assertEqualsWithDelta($scored->user->components()[0]['picks'], $user->receivedPickWorth(), 1e-9);
    }

    public function testSidesCarrySentAndReceivedFromTheCandidate(): void
    {
        $deal = VetoDealFactory::youngerBetterCheaper()['deal'];

        self::assertSame([$deal->user, $deal->partner], $deal->sides());
        self::assertSame(VetoDealFactory::USER_TID, $deal->user->tid);
        self::assertSame(VetoDealFactory::PARTNER_TID, $deal->partner->tid);
        self::assertSame($deal->candidate->userPids, self::pids($deal->user->sent));
        self::assertSame($deal->candidate->partnerPids, self::pids($deal->user->received));
        self::assertSame(self::pids($deal->user->sent), self::pids($deal->partner->received));
        self::assertSame(self::pids($deal->user->received), self::pids($deal->partner->sent));
        self::assertSame($deal->partner, $deal->other($deal->user));
        self::assertSame($deal->user, $deal->other($deal->partner));
    }

    public function testMirrorCandidateSwapsSides(): void
    {
        $fixture = VetoDealFactory::youngerBetterCheaper();
        $c = $fixture['deal']->candidate;
        $mirror = VetoDealFactory::view(
            $fixture['snapshot'],
            new TradeCandidate($c->partnerTid, $c->userTid, $c->partnerPids, $c->partnerPickIds, $c->userPids, $c->userPickIds),
        );

        self::assertSame(VetoDealFactory::PARTNER_TID, $mirror->user->tid);
        self::assertSame(VetoDealFactory::USER_TID, $mirror->partner->tid);
        self::assertSame(self::pids($fixture['deal']->partner->sent), self::pids($mirror->user->sent));
    }

    public function testPairsYieldsEveryGiveReceivePair(): void
    {
        $deal = VetoDealFactory::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [],
            [],
            [VetoDealFactory::uid(10), VetoDealFactory::uid(11)],
            [],
            [VetoDealFactory::pid(2, 10), VetoDealFactory::pid(2, 11)],
            [],
        )['deal'];

        $pairs = [];
        foreach ($deal->pairs($deal->user) as [$given, $got]) {
            $pairs[] = [$given->pid, $got->pid];
        }

        self::assertSame([
            [VetoDealFactory::uid(10), VetoDealFactory::pid(2, 10)],
            [VetoDealFactory::uid(10), VetoDealFactory::pid(2, 11)],
            [VetoDealFactory::uid(11), VetoDealFactory::pid(2, 10)],
            [VetoDealFactory::uid(11), VetoDealFactory::pid(2, 11)],
        ], $pairs);
    }

    public function testPairsEmptyWhenOneSideSendsNothing(): void
    {
        $deal = VetoDealFactory::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [],
            [],
            [],
            [],
            [VetoDealFactory::pid(2, 10)],
            [],
        )['deal'];

        self::assertSame([], iterator_to_array($deal->pairs($deal->user), false));
        self::assertSame([], iterator_to_array($deal->pairs($deal->partner), false));
    }

    public function testNegativeSurplusAbsorbedCountsOnlyFutureSeasons(): void
    {
        $deal = VetoDealFactory::youngerBetterCheaper()['deal'];
        $pid = $deal->user->received[0]->pid;
        $tweaked = VetoDealFactory::withSurplus($deal, $deal->user, $pid, [0 => -50, 1 => -100, 2 => -100, 3 => 0]);

        self::assertSame(200, $tweaked->user->negativeSurplusAbsorbed());
    }

    public function testCapRoomConsumedFlooredAtZero(): void
    {
        $falling = self::payrollDeal(700, 300);
        $rising = self::payrollDeal(300, 700);

        self::assertSame(0, $falling->user->capRoomConsumed());
        self::assertGreaterThan(0, $falling->partner->capRoomConsumed());
        self::assertGreaterThan(0, $rising->user->capRoomConsumed());
        self::assertSame(0, $rising->partner->capRoomConsumed());
    }

    public function testSideDeltaAndSurplusComeFromScoredDealAndSnapshot(): void
    {
        $fixture = VetoDealFactory::youngerBetterCheaper();
        $scored = DealScorer::score($fixture['snapshot'], $fixture['deal']->candidate, 'fixture');
        $user = $fixture['deal']->user;

        self::assertEquals($scored->userDelta, $user->delta);
        self::assertEquals($scored->partnerDelta, $fixture['deal']->partner->delta);
        self::assertEquals($scored->user, $user->gain);
        self::assertSame('fixture', $fixture['deal']->shapeId);
        foreach ($user->received as $p) {
            self::assertArrayHasKey($p->pid, $user->surplusByPid);
        }
    }

    /**
     * @param list<ProjectionPlayer> $players
     * @return list<int>
     */
    private static function pids(array $players): array
    {
        return array_map(static fn (ProjectionPlayer $p): int => $p->pid, $players);
    }

    private static function payrollDeal(int $userSalary, int $partnerSalary): DealView
    {
        return VetoDealFactory::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [7 => ['salary' => [1 => $userSalary, 2 => $userSalary]]],
            [7 => ['salary' => [1 => $partnerSalary, 2 => $partnerSalary]]],
            [VetoDealFactory::uid(7)],
            [],
            [VetoDealFactory::pid(2, 7)],
            [],
        )['deal'];
    }
}
