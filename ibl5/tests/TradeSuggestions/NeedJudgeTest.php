<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use League\League;
use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\RosterFactory;
use TradeSuggestions\Judgment;
use TradeSuggestions\LeagueStarterRanks;
use TradeSuggestions\Need;
use TradeSuggestions\NeedJudge;
use TradeSuggestions\NeedKind;
use TradeSuggestions\NeedPairMatcher;
use TradeSuggestions\NeedsCard;
use TradeSuggestions\PairedCandidate;
use TradeSuggestions\Pairing;
use TradeSuggestions\PickTier;
use TradeSuggestions\RejectReason;
use TradeSuggestions\SuggestionPick;
use TradeSuggestions\TeamAssets;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;

final class NeedJudgeTest extends TestCase
{
    public function testDealHelpingOnlyOneSideIsRejected(): void
    {
        // No sweetener: the absorber pays a roster spot and gets nothing back.
        $result = self::dump(userPayroll: 7100, pickTier: null);

        self::assertSame(RejectReason::OneSided, $result);
    }

    public function testOverCapSideMustLandAtOrUnderHardCap(): void
    {
        $atCap = self::dump(userPayroll: 7600, pickTier: PickTier::Late);
        self::assertInstanceOf(Judgment::class, $atCap);
        self::assertSame(League::HARD_CAP_MAX, $atCap->userPayrollAfter);

        self::assertSame(RejectReason::CapStillOver, self::dump(userPayroll: 7601, pickTier: PickTier::Late));
    }

    public function testFutureYearOverageOnlyDiscountsGain(): void
    {
        // Partner's next-season payroll goes from 7000 to 7300: weighted
        // increase 105, so 10 off a gain of 40.
        $result = self::dump(
            userPayroll: 7100,
            pickTier: PickTier::Late,
            dumpedLater: [300],
            partnerNextSeason: 7000,
        );

        self::assertInstanceOf(Judgment::class, $result);
        self::assertSame(30, $result->partnerGain);
    }

    public function testRequiredTierFollowsOverpayBoundaries(): void
    {
        self::assertSame(0, NeedJudge::requiredTier(999));
        self::assertSame(1, NeedJudge::requiredTier(1000));
        self::assertSame(1, NeedJudge::requiredTier(2499));
        self::assertSame(2, NeedJudge::requiredTier(2500));
        self::assertSame(2, NeedJudge::requiredTier(3999));
        self::assertSame(3, NeedJudge::requiredTier(4000));
    }

    public function testAbsorberRejectsDumpBelowRequiredPayment(): void
    {
        // 1000 now plus 1000 for two more years owes 3000: a Late first is
        // required, a Second is short.
        $short = self::dump(userPayroll: 7100, pickTier: PickTier::Second, dumpedSalary: 1000, dumpedLater: [1000, 1000]);
        self::assertSame(RejectReason::PaymentShort, $short);

        $paid = self::dump(userPayroll: 7100, pickTier: PickTier::Late, dumpedSalary: 1000, dumpedLater: [1000, 1000]);
        self::assertInstanceOf(Judgment::class, $paid);
        self::assertSame(2, $paid->requiredTier);
        self::assertSame(2, $paid->paidTier);
    }

    public function testYoungPlayerPaymentIsNeverZero(): void
    {
        $judge = new NeedJudge();
        $unranked = RosterFactory::player(1, 'C', age: 22);
        self::assertSame(1, $judge->paidTier([$unranked], [], RosterFactory::emptyRanks()));

        $good = RosterFactory::player(2, 'C', age: 22, points: 300);
        self::assertSame(2, $judge->paidTier([$good], [], RosterFactory::ranks('C', null, [100, 50, 20])));

        $veteran = RosterFactory::player(3, 'C', age: 25);
        self::assertSame(0, $judge->paidTier([$veteran], [], RosterFactory::emptyRanks()));
    }

    public function testYoungPlayerDumpedByOverCapSideIsNotPayment(): void
    {
        // Team 1 is 1100 over. The matcher picks bench pid 10 (age 23, 1200
        // owed) as the dump, which owes a tier 1 sweetener. Team 1 has no
        // picks and no other young bench player, so nothing pays for it.
        $starter = RosterFactory::player(11, 'C', salary: 300, dcMinutes: 30);
        $user = new TeamAssets(1, [RosterFactory::player(10, 'C', age: 23, salary: 1200), $starter], [], null, League::HARD_CAP_MAX - 8100);
        $userCard = new NeedsCard(1, [new Need(NeedKind::OverHardCap, 1100)], 8100, [], TeamMode::Rebuild, 0.5);
        $partner = RosterFactory::team(2, [RosterFactory::player(20, 'PG', dcMinutes: 30)], 4000);
        $partnerCard = new NeedsCard(2, [new Need(NeedKind::RoomToAbsorb, 3000)], 4000, [], TeamMode::Rebuild, 0.5);
        $ranks = RosterFactory::emptyRanks();

        $candidates = array_values(iterator_to_array((new NeedPairMatcher())->generate(
            $userCard,
            $user,
            [2 => $partnerCard],
            [2 => $partner],
            RosterFactory::depth(['C' => $starter]),
            [2 => RosterFactory::depth([])],
            $ranks,
        ), false));

        self::assertCount(1, $candidates);
        self::assertSame([10], $candidates[0]->candidate->userPids);
        self::assertSame([10], $candidates[0]->dumpPids);
        self::assertSame(
            RejectReason::PaymentShort,
            (new NeedJudge())->judge($candidates[0], $userCard, $partnerCard, $user, $partner, $ranks),
        );
    }

    public function testYoungLongContractSentForExpiringIsNotPayment(): void
    {
        // Team 1 is capped out and swaps pid 10 (age 23, 3000 owed) for an
        // expiring 1000. Team 2 takes on 2000 more and is owed a tier 1
        // sweetener; the long contract itself does not pay it.
        $starter = RosterFactory::player(11, 'C', salary: 300, dcMinutes: 30);
        $user = RosterFactory::team(1, [RosterFactory::player(10, 'C', age: 23, salary: 1000, years: 3, laterSalaries: [1000, 1000]), $starter], 6000);
        $userCard = new NeedsCard(1, [new Need(NeedKind::CappedOut, 2000)], 6000, [], TeamMode::Rebuild, 0.5);
        $partner = RosterFactory::team(2, [RosterFactory::player(20, 'C', salary: 1000), RosterFactory::player(21, 'PG', dcMinutes: 30)], 4000);
        $partnerCard = new NeedsCard(2, [new Need(NeedKind::RoomToAbsorb, 3000)], 4000, [], TeamMode::Rebuild, 0.5);
        $ranks = RosterFactory::emptyRanks();

        $candidates = array_values(iterator_to_array((new NeedPairMatcher())->generate(
            $userCard,
            $user,
            [2 => $partnerCard],
            [2 => $partner],
            RosterFactory::depth(['C' => $starter]),
            [2 => RosterFactory::depth([])],
            $ranks,
        ), false));

        self::assertCount(1, $candidates);
        self::assertSame(Pairing::LengthForExpiring, $candidates[0]->pairing);
        self::assertSame([10], $candidates[0]->dumpPids);
        self::assertSame(
            RejectReason::PaymentShort,
            (new NeedJudge())->judge($candidates[0], $userCard, $partnerCard, $user, $partner, $ranks),
        );
    }

    public function testExtraIncomingPlayerCostsRosterSpot(): void
    {
        $oneIn = self::dump(userPayroll: 7100, pickTier: PickTier::Late);
        self::assertInstanceOf(Judgment::class, $oneIn);
        self::assertSame(40, $oneIn->partnerGain);

        $even = self::dump(userPayroll: 7100, pickTier: PickTier::Late, partnerSendsBack: true);
        self::assertInstanceOf(Judgment::class, $even);
        self::assertSame(50, $even->partnerGain);
    }

    public function testLesserPlayerInPackageCountsHalf(): void
    {
        $user = new TeamAssets(1, [
            RosterFactory::player(10, 'C', salary: 500),
            RosterFactory::player(11, 'C', salary: 500),
        ], [new SuggestionPick(100, 1, 2027, 1, 0.0, PickTier::Lottery)], null, League::HARD_CAP_MAX - 6000);
        $partner = RosterFactory::team(2, [
            RosterFactory::player(30, 'SF', salary: 400, points: 300),
            RosterFactory::player(31, 'PF', salary: 400, points: 300),
            RosterFactory::player(32, 'SF', salary: 300),
            RosterFactory::player(33, 'PF', salary: 300),
        ], 5000);
        $ranks = LeagueStarterRanks::build([
            RosterFactory::depth([
                'SF' => RosterFactory::player(901, 'SF', points: 200),
                'PF' => RosterFactory::player(902, 'PF', points: 200),
            ]),
            RosterFactory::depth([
                'SF' => RosterFactory::player(903, 'SF', points: 100),
                'PF' => RosterFactory::player(904, 'PF', points: 100),
            ]),
        ]);
        $paired = new PairedCandidate(
            new TradeCandidate(1, 2, [10], [100], [30, 31], []),
            Pairing::Consolidation,
            new Need(NeedKind::Consolidate, 1),
            new Need(NeedKind::RoomToAbsorb, 2000),
        );

        $result = (new NeedJudge())->judge(
            $paired,
            self::card(1, NeedKind::Consolidate, TeamMode::Rebuild, 6000),
            self::card(2, NeedKind::RoomToAbsorb, TeamMode::Rebuild, 5000),
            $user,
            $partner,
            $ranks,
        );

        // Both incoming players rank 1st: 100 + 50 for the lesser, minus 10
        // for the net extra player.
        self::assertInstanceOf(Judgment::class, $result);
        self::assertSame(140, $result->userGain);
    }

    public function testWinNowSideSendingStarterIsRejected(): void
    {
        $result = self::dump(userPayroll: 7100, pickTier: PickTier::Late, userMode: TeamMode::WinNow);

        self::assertSame(RejectReason::ImportantWorsened, $result);
    }

    public function testLosingTopHalfStarterWithoutReplacementIsRejected(): void
    {
        $result = self::dump(
            userPayroll: 7100,
            pickTier: PickTier::Late,
            userMode: TeamMode::WinNow,
            dumpedPoints: 300,
            rivalCenters: [100, 50],
        );

        self::assertSame(RejectReason::ImportantWorsened, $result);
    }

    /**
     * Team 1 (over the cap) sends its starting center, pid 10, to team 2,
     * which has room. Team 1 keeps a backup center, pid 11.
     *
     * @param list<int> $dumpedLater
     * @param list<int> $rivalCenters
     */
    private static function dump(
        int $userPayroll,
        ?PickTier $pickTier,
        int $dumpedSalary = 600,
        array $dumpedLater = [],
        int $partnerNextSeason = 0,
        bool $partnerSendsBack = false,
        TeamMode $userMode = TeamMode::Rebuild,
        ?int $dumpedPoints = null,
        array $rivalCenters = [],
    ): Judgment|RejectReason {
        $dumped = RosterFactory::player(10, 'C', salary: $dumpedSalary, years: 1 + count($dumpedLater), dcMinutes: 30, laterSalaries: $dumpedLater, points: $dumpedPoints);
        $picks = $pickTier === null ? [] : [new SuggestionPick(100, 1, 2027, 1, 0.0, $pickTier)];
        $user = new TeamAssets(1, [$dumped, RosterFactory::player(11, 'C', salary: 300)], $picks, null, League::HARD_CAP_MAX - $userPayroll);

        $partnerPlayers = [
            RosterFactory::player(20, 'C', salary: 400, laterSalaries: $partnerNextSeason > 0 ? [$partnerNextSeason] : []),
            RosterFactory::player(21, 'PF', salary: 100),
            RosterFactory::player(22, 'PF', salary: 100),
        ];
        $partner = RosterFactory::team(2, $partnerPlayers, 5000);

        $ranks = $rivalCenters === []
            ? RosterFactory::emptyRanks()
            : RosterFactory::ranks('C', $dumped, $rivalCenters);

        $paired = new PairedCandidate(
            new TradeCandidate(1, 2, [10], $pickTier === null ? [] : [100], $partnerSendsBack ? [21] : [], []),
            Pairing::SalaryDump,
            new Need(NeedKind::OverHardCap, $userPayroll - League::HARD_CAP_MAX),
            new Need(NeedKind::RoomToAbsorb, 2000),
        );

        return (new NeedJudge())->judge(
            $paired,
            self::card(1, NeedKind::OverHardCap, $userMode, $userPayroll),
            self::card(2, NeedKind::RoomToAbsorb, TeamMode::Rebuild, 5000),
            $user,
            $partner,
            $ranks,
        );
    }

    private static function card(int $tid, NeedKind $top, TeamMode $mode, int $payroll): NeedsCard
    {
        return new NeedsCard($tid, [new Need($top, 1)], $payroll, [], $mode, 0.5);
    }
}
