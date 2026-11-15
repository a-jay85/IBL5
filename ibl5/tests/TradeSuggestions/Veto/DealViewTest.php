<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Veto;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Veto\Fixtures\VetoDealFactory;
use TradeSuggestions\Engine\DealScorer;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\PairRecordMemo;
use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\Projection\InjuryStatus;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Valuation\PickWorth;
use TradeSuggestions\Veto\DealView;
use TradeSuggestions\Veto\SideView;

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

    /**
     * A sent pick is worth what it would be as the first pick in, so it takes
     * the glut discount only when the roster has no open spot.
     *
     * @return iterable<string, array{int, float}>
     */
    public static function sentPickRosterProvider(): iterable
    {
        yield 'full roster, no open spot' => [15, PickWorth::GLUT_FACTOR];
        yield 'one open spot' => [14, 1.0];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sentPickRosterProvider')]
    public function testSentPickIsValuedAsTheFirstPickIn(int $userRoster, float $glut): void
    {
        $fixture = VetoDealFactory::deal([TeamMode::Middle, TeamMode::Middle], [], [], [], [11], [], [], userRoster: $userRoster);
        $s = $fixture['snapshot'];
        $user = $fixture['deal']->user;
        $unDiscounted = SideGain::pickWorth($s, $s->picks[11], TeamMode::Middle, 99, 1);

        self::assertGreaterThan(0.0, $unDiscounted);
        self::assertEqualsWithDelta($unDiscounted * $glut, $user->pickWorthById[11], 1e-9);
    }

    /**
     * The open spot is counted this season: a one-year deal that frees a spot
     * next season does not lift the glut discount now.
     */
    public function testSentPickUsesThisSeasonsOpenSpots(): void
    {
        $fixture = VetoDealFactory::deal([TeamMode::Middle, TeamMode::Middle], [13 => ['salary' => [1 => 300]]], [], [], [11], [], [], userRoster: 15);
        $s = $fixture['snapshot'];
        $user = $fixture['deal']->user;
        $unDiscounted = SideGain::pickWorth($s, $s->picks[11], TeamMode::Middle, 99, 1);

        self::assertSame(15, $user->after[0]->rosterCount);
        self::assertSame(14, $user->after[1]->rosterCount);
        self::assertEqualsWithDelta($unDiscounted * PickWorth::GLUT_FACTOR, $user->pickWorthById[11], 1e-9);
    }

    public function testAMemoPassedInIsTheOneTheRebuildFills(): void
    {
        // Sending a starter for nothing reshuffles the starter groups, so the rebuild compares pairs.
        $fixture = VetoDealFactory::deal([TeamMode::Middle, TeamMode::Middle], [], [], [VetoDealFactory::uid(2)], [], [], []);
        $s = $fixture['snapshot'];
        $memo = new PairRecordMemo();

        DealView::fromScoredDeal($s, DealScorer::score($s, $fixture['deal']->candidate, 'fixture'), $memo);

        self::assertGreaterThan(0, $memo->size());
    }

    public function testSentPlayersCarryTheirSurplusToo(): void
    {
        $fixture = VetoDealFactory::youngerBetterCheaper();
        $uid = VetoDealFactory::uid(7);

        self::assertSame($fixture['snapshot']->surplusBySeason($uid), $fixture['deal']->user->surplusByPid[$uid]);
    }

    public function testReceivedPlayerCarriesTheSnapshotInjury(): void
    {
        $fixture = self::injuredReceived();
        $pid = VetoDealFactory::pid(2, 7);
        $status = $fixture['snapshot']->injury[$pid];

        self::assertNotSame(InjuryStatus::Healthy, $status);
        self::assertSame($status, $fixture['deal']->user->injuryByPid[$pid]);
    }

    public function testPlayerWithNoInjuryEntryIsHealthyAndFullyAvailable(): void
    {
        $fixture = self::injuredReceived();
        $s = $fixture['snapshot'];
        $pid = VetoDealFactory::pid(2, 7);
        $injury = $s->injury;
        unset($injury[$pid]);
        $bare = new LeagueSnapshot(...[...get_object_vars($s), 'injury' => $injury]);

        $user = VetoDealFactory::view($bare, $fixture['deal']->candidate)->user;

        self::assertSame(1.0, $user->availabilityByPid[$pid]);
        self::assertSame(InjuryStatus::Healthy, $user->injuryByPid[$pid]);
    }

    /**
     * Availability covers every player on both teams, including a
     * one-year player who is gone next season and one who is not first
     * on his roster.
     */
    public function testAvailabilityCoversEveryPlayerOnBothTeams(): void
    {
        $hurt = ['injured' => 200, 'salary' => [1 => 300]];
        $fixture = VetoDealFactory::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [3 => $hurt],
            [3 => $hurt],
            [VetoDealFactory::uid(7)],
            [],
            [VetoDealFactory::pid(2, 7)],
            [],
        );
        $s = $fixture['snapshot'];
        $availability = $fixture['deal']->user->availabilityByPid;

        foreach ([VetoDealFactory::uid(3), VetoDealFactory::pid(2, 3)] as $pid) {
            $weight = $s->injury[$pid]->availabilityWeight();
            self::assertLessThan(1.0, $weight);
            self::assertSame($weight, $availability[$pid]);
        }
        foreach ([...array_keys($s->lineup(0, VetoDealFactory::USER_TID)->players), ...array_keys($s->lineup(0, VetoDealFactory::PARTNER_TID)->players)] as $pid) {
            self::assertArrayHasKey($pid, $availability);
        }
        self::assertSame($availability, $fixture['deal']->partner->availabilityByPid);
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

    public function testReceivedPlayersAgedIsAListOfOnlyTheOldOnes(): void
    {
        $deal = self::twoReceived();
        $old = $deal->user->receivedPlayersAged(29);

        self::assertTrue(array_is_list($old));
        self::assertSame([VetoDealFactory::pid(2, 8)], array_map(static fn (ProjectionPlayer $p): int => $p->pid, $old));
    }

    public function testNegativeSurplusAbsorbedSumsEveryReceivedPlayer(): void
    {
        $deal = self::twoReceived();
        $deal = VetoDealFactory::withSurplus($deal, $deal->user, VetoDealFactory::pid(2, 7), [0 => 0, 1 => -100, 2 => 0, 3 => 0]);
        $deal = VetoDealFactory::withSurplus($deal, $deal->user, VetoDealFactory::pid(2, 8), [0 => 0, 1 => -30, 2 => 0, 3 => 0]);

        self::assertSame(130, $deal->user->negativeSurplusAbsorbed());
    }

    /**
     * DealView records a worth for every pick; a SideView built without one
     * counts that pick as worth nothing.
     */
    public function testReceivedPickWithNoRecordedWorthAddsNothing(): void
    {
        $fixture = VetoDealFactory::deal([TeamMode::Middle, TeamMode::Middle], [], [], [], [], [], [VetoDealFactory::PARTNER_PICK_ID]);
        $side = $fixture['deal']->user;
        self::assertGreaterThan(0.0, $side->receivedPickWorth());

        $bare = new SideView(
            $side->tid,
            $side->mode,
            $side->sent,
            $side->received,
            $side->sentPicks,
            $side->receivedPicks,
            $side->before,
            $side->after,
            $side->gain,
            $side->situation,
            $side->surplusByPid,
            $side->delta,
            [],
            $side->availabilityByPid,
        );

        self::assertCount(1, $bare->receivedPicks);
        self::assertSame(0.0, $bare->receivedPickWorth());
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

    /** The user gets a 25-year-old (index 7), then a 31-year-old (index 8). */
    private static function twoReceived(): DealView
    {
        return VetoDealFactory::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [],
            [7 => ['age' => 25], 8 => ['age' => 31]],
            [VetoDealFactory::uid(7)],
            [],
            [VetoDealFactory::pid(2, 7), VetoDealFactory::pid(2, 8)],
            [],
        )['deal'];
    }

    /**
     * The user swaps index 7 for the partner's injured index 7.
     *
     * @return array{snapshot: LeagueSnapshot, deal: DealView}
     */
    private static function injuredReceived(): array
    {
        return VetoDealFactory::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [],
            [7 => ['injured' => 200]],
            [VetoDealFactory::uid(7)],
            [],
            [VetoDealFactory::pid(2, 7)],
            [],
        );
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
