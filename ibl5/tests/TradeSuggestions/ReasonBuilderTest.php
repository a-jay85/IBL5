<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use League\League;
use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\RosterFactory;
use TradeSuggestions\Judgment;
use TradeSuggestions\LeagueStarterRanks;
use TradeSuggestions\Need;
use TradeSuggestions\NeedKind;
use TradeSuggestions\NeedsCard;
use TradeSuggestions\PairedCandidate;
use TradeSuggestions\Pairing;
use TradeSuggestions\ReasonBuilder;
use TradeSuggestions\SuggestionPick;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamAssets;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;

/**
 * Every line must be true for the numbers in the deal, and quotes only raw
 * stats, ranks within a slot, money, years, age or record.
 */
class ReasonBuilderTest extends TestCase
{
    public function testOverHardCapReasonStatesOverageAndMargin(): void
    {
        self::assertSame("You're 653 over the hard cap. This gets you 47 under.", self::overCapReason(true));
    }

    public function testOverHardCapReasonSaysExactlyWhenUnderIsZero(): void
    {
        $over = RosterFactory::team(1, [RosterFactory::player(10, 'C', salary: 653)], 7653);

        $reason = self::reason(
            new NeedsCard(1, [new Need(NeedKind::OverHardCap, 653)], 7653, [], TeamMode::Rebuild, 0.5),
            $over,
            RosterFactory::team(2, [], 5000),
            self::paired([10], [], []),
            RosterFactory::emptyRanks(),
            new Judgment(100, 30, 0, 0, League::HARD_CAP_MAX, 5653),
        );

        self::assertSame("You're 653 over the hard cap. This gets you exactly to the hard cap.", $reason);
    }

    public function testPartnerVoiceUsesThey(): void
    {
        self::assertSame("They're 653 over the hard cap. This gets them 47 under.", self::overCapReason(false));
    }

    public function testWeakStarterReasonStatesRanks(): void
    {
        self::assertSame(
            'Your starting C ranks 26th of 28 starting Cs in points per 36. Player 20 would rank 9th (14.2 ppg).',
            self::weakStarter(),
        );
    }

    public function testLogjamReasonCountsPlayersAtSlot(): void
    {
        self::assertSame(
            'You have 3 SFs getting minutes. This turns Player 10 into a starting C.',
            self::logjam(),
        );
    }

    public function testSweetenerClauseNamesYearsAndPick(): void
    {
        $dumped = RosterFactory::player(10, 'C', salary: 700, years: 3);
        $pick = new SuggestionPick(100, 1, 2027, 2, 0.0);
        $user = new TeamAssets(1, [$dumped, RosterFactory::player(11, 'C')], [$pick], null, League::HARD_CAP_MAX - 7653);

        $reason = self::reason(
            new NeedsCard(1, [new Need(NeedKind::OverHardCap, 653)], 7653, [], TeamMode::Rebuild, 0.5),
            $user,
            RosterFactory::team(2, [], 5000),
            self::paired([10], [100], []),
            RosterFactory::emptyRanks(),
            new Judgment(100, 30, 1, 1, 6953, 5700),
        );

        self::assertSame(
            "You're 653 over the hard cap. This gets you 47 under. They take on 3 more years of salary, so you send the 2027 Round 2 pick.",
            $reason,
        );
    }

    public function testBadContractReasonGolden(): void
    {
        self::assertSame('Player 10 is owed 2500 after this season at age 31. This clears it.', self::badContract());
    }

    public function testDeadWeightReasonGolden(): void
    {
        self::assertSame('Player 10 plays 4 minutes and costs 650. This frees the salary.', self::deadWeight());
    }

    public function testExpiringStarReasonGolden(): void
    {
        self::assertSame(
            'Player 10 is in the last year of his deal and you win 30% of games. This turns him into the 2027 Round 1 pick.',
            self::expiringStar(),
        );
    }

    public function testCappedOutReasonGolden(): void
    {
        self::assertSame(
            "You're 200 under the hard cap. This swaps 2 years of Player 10's salary for an expiring deal.",
            self::cappedOut(),
        );
    }

    public function testConsolidateReasonGolden(): void
    {
        self::assertSame(
            'You get Player 20, 1st of 2 starting Cs in points per 36 (14.2 ppg, 0.0 rpg, 0.0 apg).',
            self::consolidate(),
        );
    }

    public function testRoomToAbsorbReasonGolden(): void
    {
        self::assertSame(
            'You have 2000 of room under the hard cap. Taking on Player 20 earns you the 2027 Round 1 pick.',
            self::roomToAbsorb(),
        );
    }

    public function testDumpedYoungPlayerIsNotNamedAsPayment(): void
    {
        $reason = self::reason(
            new NeedsCard(1, [new Need(NeedKind::RoomToAbsorb, 2000)], 5000, [], TeamMode::Rebuild, 0.5),
            RosterFactory::team(1, [], 5000),
            self::withPick(RosterFactory::team(2, [RosterFactory::player(20, 'C', age: 23)]), new SuggestionPick(200, 2, 2027, 1, 0.0)),
            new PairedCandidate(
                new TradeCandidate(1, 2, [], [], [20], [200]),
                Pairing::SalaryDump,
                new Need(NeedKind::RoomToAbsorb, 1),
                new Need(NeedKind::DeadWeight, 1),
                [20],
            ),
            RosterFactory::emptyRanks(),
        );

        self::assertSame(
            'You have 2000 of room under the hard cap. Taking on Player 20 earns you the 2027 Round 1 pick.',
            $reason,
        );
    }

    public function testRebuildReasonGolden(): void
    {
        self::assertSame('Player 20 (age 22) for Player 10, age 31, fits your rebuild.', self::rebuild());
    }

    public function testWinNowReasonGolden(): void
    {
        self::assertSame(
            'Player 20 (14.2 ppg) would start at C for you, 1st of 3 in points per 36, up from 3rd.',
            self::winNow(),
        );
    }

    public function testReasonsNeverMentionRating(): void
    {
        $reasons = [
            self::overCapReason(true),
            self::overCapReason(false),
            self::weakStarter(),
            self::logjam(),
            self::badContract(),
            self::deadWeight(),
            self::expiringStar(),
            self::cappedOut(),
            self::consolidate(),
            self::roomToAbsorb(),
            self::rebuild(),
            self::winNow(),
        ];

        foreach ($reasons as $reason) {
            self::assertNotNull($reason);
            self::assertDoesNotMatchRegularExpression('/\b(ovr|rating|value)\b/i', $reason);
        }
    }

    public function testOrdinalSuffixesTeens(): void
    {
        self::assertSame(
            ['1st', '2nd', '3rd', '4th', '11th', '12th', '13th', '21st', '22nd', '23rd', '111th'],
            array_map(ReasonBuilder::ordinal(...), [1, 2, 3, 4, 11, 12, 13, 21, 22, 23, 111]),
        );
    }

    public function testMissingRankReturnsNull(): void
    {
        // The user's starting center has no production, so it has no rank.
        $user = RosterFactory::team(1, [RosterFactory::player(10, 'C', dcMinutes: 30)]);
        $partner = RosterFactory::team(2, [RosterFactory::player(20, 'C', dcMinutes: 30, points: 300)]);

        $reason = self::reason(
            new NeedsCard(1, [new Need(NeedKind::WeakStarter, 3, 'C')], 6000, [], TeamMode::Rebuild, 0.5),
            $user,
            $partner,
            self::paired([], [], [20]),
            RosterFactory::ranks('C', null, [400, 200]),
        );

        self::assertNull($reason);
    }

    public function testBuilderLeavesPlayerNameRawForTheView(): void
    {
        $dead = new SuggestionPlayer(
            pid: 10,
            name: '<script>alert(1)</script>',
            pos: 'C',
            age: 29,
            injured: false,
            currentSalary: 650,
            yearsRemaining: 1,
            futureSalary: 0,
            dcMinutes: 4,
        );
        $user = RosterFactory::team(1, [$dead, RosterFactory::player(11, 'C')]);

        $reason = self::reason(
            new NeedsCard(1, [new Need(NeedKind::DeadWeight, 650, null, [10])], 6000, [], TeamMode::Rebuild, 0.5),
            $user,
            RosterFactory::team(2, [], 5000),
            self::paired([10], [], []),
            RosterFactory::emptyRanks(),
        );

        self::assertSame('<script>alert(1)</script> plays 4 minutes and costs 650. This frees the salary.', $reason);
    }

    private static function overCapReason(bool $userSide): ?string
    {
        $over = RosterFactory::team(1, [RosterFactory::player(10, 'C', salary: 700), RosterFactory::player(11, 'C')], 7653);
        $absorber = RosterFactory::team(2, [], 5000);
        $card = new NeedsCard($userSide ? 1 : 2, [new Need(NeedKind::OverHardCap, 653)], 7653, [], TeamMode::Rebuild, 0.5);

        return (new ReasonBuilder())->forSide(
            $userSide ? self::paired([10], [], []) : self::paired([], [], [10]),
            new Judgment(100, 30, 0, 0, $userSide ? 6953 : 5700, $userSide ? 5700 : 6953),
            $userSide,
            $card,
            $over,
            $absorber,
            RosterFactory::emptyRanks(),
        );
    }

    private static function weakStarter(): ?string
    {
        $starter = RosterFactory::player(10, 'C', dcMinutes: 30, points: 100);
        $rivals = [50, 60];
        for ($i = 0; $i < 17; $i++) {
            $rivals[] = 200 + $i;
        }
        for ($i = 0; $i < 8; $i++) {
            $rivals[] = 500 + $i;
        }

        return self::reason(
            new NeedsCard(1, [new Need(NeedKind::WeakStarter, 25, 'C')], 6000, [], TeamMode::Rebuild, 0.5),
            RosterFactory::team(1, [$starter]),
            RosterFactory::team(2, [RosterFactory::player(20, 'C', points: 426)]),
            self::paired([], [], [20]),
            RosterFactory::ranks('C', $starter, $rivals),
        );
    }

    private static function logjam(): ?string
    {
        return self::reason(
            // NeedsCardBuilder sets Logjam severity to rotation count - 2 (3 SFs -> 1).
            new NeedsCard(1, [new Need(NeedKind::Logjam, 1, 'SF', [10])], 6000, [], TeamMode::Rebuild, 0.5),
            RosterFactory::team(1, [
                RosterFactory::player(10, 'SF', dcMinutes: 20),
                RosterFactory::player(11, 'SF', dcMinutes: 20),
                RosterFactory::player(12, 'SF', dcMinutes: 20),
            ]),
            RosterFactory::team(2, [RosterFactory::player(20, 'C')]),
            self::paired([10], [], [20]),
            RosterFactory::emptyRanks(),
        );
    }

    private static function badContract(): ?string
    {
        return self::reason(
            new NeedsCard(1, [new Need(NeedKind::BadContract, 1, null, [10])], 6000, [], TeamMode::Rebuild, 0.5),
            RosterFactory::team(1, [RosterFactory::player(10, 'C', age: 31, salary: 900, years: 3, laterSalaries: [1200, 1300])]),
            RosterFactory::team(2, [], 5000),
            self::paired([10], [], []),
            RosterFactory::emptyRanks(),
        );
    }

    private static function deadWeight(): ?string
    {
        return self::reason(
            new NeedsCard(1, [new Need(NeedKind::DeadWeight, 650, null, [10])], 6000, [], TeamMode::Rebuild, 0.5),
            RosterFactory::team(1, [RosterFactory::player(10, 'C', salary: 650, dcMinutes: 4)]),
            RosterFactory::team(2, [], 5000),
            self::paired([10], [], []),
            RosterFactory::emptyRanks(),
        );
    }

    private static function expiringStar(): ?string
    {
        return self::reason(
            new NeedsCard(1, [new Need(NeedKind::ExpiringStarOnLoser, 1, 'C', [10])], 6000, [], TeamMode::Rebuild, 0.3),
            RosterFactory::team(1, [RosterFactory::player(10, 'C')]),
            self::withPick(RosterFactory::team(2, []), new SuggestionPick(200, 2, 2027, 1, 0.0)),
            self::paired([10], [], [], [200]),
            RosterFactory::emptyRanks(),
        );
    }

    private static function cappedOut(): ?string
    {
        return self::reason(
            new NeedsCard(1, [new Need(NeedKind::CappedOut, 1, null, [10])], 6800, [], TeamMode::WinNow, 0.5),
            RosterFactory::team(1, [RosterFactory::player(10, 'C', years: 3, laterSalaries: [900, 800])], 6800),
            RosterFactory::team(2, [], 5000),
            self::paired([10], [], []),
            RosterFactory::emptyRanks(),
        );
    }

    private static function consolidate(): ?string
    {
        return self::reason(
            new NeedsCard(1, [new Need(NeedKind::Consolidate, 1)], 6000, [], TeamMode::WinNow, 0.5),
            RosterFactory::team(1, []),
            RosterFactory::team(2, [RosterFactory::player(20, 'C', points: 426)]),
            self::paired([], [], [20]),
            RosterFactory::ranks('C', null, [100, 200]),
        );
    }

    private static function roomToAbsorb(): ?string
    {
        return self::reason(
            new NeedsCard(1, [new Need(NeedKind::RoomToAbsorb, 2000)], 5000, [], TeamMode::Rebuild, 0.5),
            RosterFactory::team(1, [], 5000),
            self::withPick(RosterFactory::team(2, [RosterFactory::player(20, 'C')]), new SuggestionPick(200, 2, 2027, 1, 0.0)),
            self::paired([], [], [20], [200]),
            RosterFactory::emptyRanks(),
        );
    }

    private static function rebuild(): ?string
    {
        return self::reason(
            new NeedsCard(1, [new Need(NeedKind::Rebuild, 1)], 6000, [], TeamMode::Rebuild, 0.5),
            RosterFactory::team(1, [RosterFactory::player(10, 'C', age: 31)]),
            RosterFactory::team(2, [RosterFactory::player(20, 'C', age: 22)]),
            self::paired([10], [], [20]),
            RosterFactory::emptyRanks(),
        );
    }

    private static function winNow(): ?string
    {
        $starter = RosterFactory::player(10, 'C', dcMinutes: 30, points: 100);

        return self::reason(
            new NeedsCard(1, [new Need(NeedKind::WinNow, 1)], 6000, [], TeamMode::WinNow, 0.5),
            RosterFactory::team(1, [$starter]),
            RosterFactory::team(2, [RosterFactory::player(20, 'C', points: 426)]),
            self::paired([], [], [20]),
            RosterFactory::ranks('C', $starter, [400, 300]),
        );
    }

    private static function reason(
        NeedsCard $card,
        TeamAssets $self,
        TeamAssets $other,
        PairedCandidate $paired,
        LeagueStarterRanks $ranks,
        ?Judgment $judgment = null,
    ): ?string {
        return (new ReasonBuilder())->forSide(
            $paired,
            $judgment ?? new Judgment(50, 50, 0, 0, 6000, 6000),
            true,
            $card,
            $self,
            $other,
            $ranks,
        );
    }

    private static function withPick(TeamAssets $team, SuggestionPick $pick): TeamAssets
    {
        return new TeamAssets($team->tid, $team->players, [$pick], $team->rosterCount, $team->capRoom);
    }

    /**
     * @param list<int> $userPids
     * @param list<int> $userPickIds
     * @param list<int> $partnerPids
     * @param list<int> $partnerPickIds
     */
    private static function paired(array $userPids, array $userPickIds, array $partnerPids, array $partnerPickIds = []): PairedCandidate
    {
        return new PairedCandidate(
            new TradeCandidate(1, 2, $userPids, $userPickIds, $partnerPids, $partnerPickIds),
            Pairing::SalaryDump,
            new Need(NeedKind::OverHardCap, 1),
            new Need(NeedKind::RoomToAbsorb, 1),
        );
    }
}
