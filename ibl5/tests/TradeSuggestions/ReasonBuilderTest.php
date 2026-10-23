<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\RosterFactory;
use TradeSuggestions\Judgment;
use TradeSuggestions\Need;
use TradeSuggestions\NeedKind;
use TradeSuggestions\NeedsCard;
use TradeSuggestions\PairedCandidate;
use TradeSuggestions\Pairing;
use TradeSuggestions\ReasonBuilder;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\SuggestionPick;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamAssets;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TeamNeedsScorer;
use TradeSuggestions\TeamScore;

/**
 * Every line must be true for the numbers in the deal. v1 printed canned text
 * ("Adds a rotation player signed beyond this season" for one-year players).
 */
class ReasonBuilderTest extends TestCase
{
    private ReasonBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ReasonBuilder();
    }

    public function testYearsLeftMatchesTheContract(): void
    {
        self::assertSame('1 year left on contract', ReasonBuilder::yearsLeft(self::player(1, 60, years: 1)));
        self::assertSame('3 years left on contract', ReasonBuilder::yearsLeft(self::player(1, 60, years: 3)));
    }

    public function testRankClaimMatchesTheRosterAfterTheTrade(): void
    {
        $incoming = self::player(50, 64, years: 1);
        $after = new TeamAssets(1, [self::player(1, 70), self::player(2, 66), $incoming, self::player(3, 60)], []);

        $options = $this->options(TeamMode::WinNow, $after, [$incoming], []);

        self::assertSame("Adds Player 50 (ovr 64, age 27, 1 year left on contract), who would be the team's 3rd-best player.", $options[0]);
    }

    public function testPlayerOutsideTopEightGetsNoRotationClaim(): void
    {
        $incoming = self::player(50, 50);
        $roster = [$incoming];
        for ($i = 1; $i <= 8; $i++) {
            $roster[] = self::player($i, 60);
        }

        $options = $this->options(TeamMode::WinNow, new TeamAssets(1, $roster, []), [$incoming], []);

        foreach ($options as $option) {
            self::assertStringNotContainsString('best player', $option);
        }
        self::assertContains('Adds Player 50 (ovr 50, age 27, 2 years left on contract).', $options);
    }

    public function testConsolidationOnlyWhenTheOnePlayerIsBetter(): void
    {
        $better = self::player(50, 68);
        $worse = self::player(51, 60);
        $out = [self::player(1, 62), self::player(2, 61)];

        $yes = $this->options(TeamMode::WinNow, new TeamAssets(1, [$better], []), [$better], $out);
        $no = $this->options(TeamMode::WinNow, new TeamAssets(1, [$worse], []), [$worse], $out);

        self::assertContains('Turns Player 1 (ovr 62) and Player 2 (ovr 61) into one better player, Player 50 (ovr 68).', $yes);
        foreach ($no as $option) {
            self::assertStringNotContainsString('one better player', $option);
        }
    }

    public function testFreedSalaryIsTheRealDifference(): void
    {
        $out = [self::player(1, 60, years: 3, futureSalary: 3200)];
        $in = [self::player(50, 58, years: 1, futureSalary: 0)];

        $options = $this->options(TeamMode::Rebuild, new TeamAssets(1, $in, []), $in, $out);

        self::assertContains('Frees $3,200K of salary owed in later contract years.', $options);
    }

    public function testNoSalaryLineWhenSalaryGrows(): void
    {
        $out = [self::player(1, 60, years: 1, futureSalary: 0)];
        $in = [self::player(50, 62, years: 3, futureSalary: 3000)];

        foreach ($this->options(TeamMode::Rebuild, new TeamAssets(1, $in, []), $in, $out) as $option) {
            self::assertStringNotContainsString('Frees', $option);
        }
    }

    public function testPickAndGapLinesUseRealValues(): void
    {
        $pick = new SuggestionPick(9, 3, 2028, 2, 2.0);
        $before = new TeamScore(0.0, ['guard' => 3, 'wing' => 4, 'big' => 1], ['big' => 1]);
        $after = new TeamScore(0.0, ['guard' => 3, 'wing' => 3, 'big' => 2], []);

        $options = $this->builder->options(TeamMode::WinNow, new TeamAssets(1, [], []), [], [], [$pick], $before, $after);

        self::assertContains('Adds a 2028 second-round pick.', $options);
        self::assertContains('Fills a gap at big: its top eight goes from 1 to 2 bigs.', $options);
    }

    /**
     * @param list<SuggestionPlayer> $in
     * @param list<SuggestionPlayer> $out
     * @return list<string>
     */
    private function options(TeamMode $mode, TeamAssets $after, array $in, array $out): array
    {
        $score = new TeamScore(0.0, array_fill_keys(TeamNeedsScorer::GROUPS, 2), []);

        return $this->builder->options($mode, $after, $in, $out, [], $score, $score);
    }

    private static function player(int $pid, int $ovr, int $years = 2, int $futureSalary = 1000): SuggestionPlayer
    {
        return new SuggestionPlayer($pid, 'Player ' . $pid, 'SF', 27, $ovr, false, 1000, $years, $futureSalary);
    }

    public function testOverHardCapReasonStatesOverageAndMargin(): void
    {
        self::assertSame("You're 653 over the hard cap. This gets you 47 under.", self::overCapReason(true));
    }

    public function testPartnerVoiceUsesThey(): void
    {
        self::assertSame("They're 653 over the hard cap. This gets them 47 under.", self::overCapReason(false));
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

        $reason = (new ReasonBuilder())->forSide(
            self::paired([], [20]),
            new Judgment(50, 50, 0, 0, 6000, 6000),
            true,
            new NeedsCard(1, [new Need(NeedKind::WeakStarter, 3, 'C')], 6000, [], TeamMode::Rebuild, 0.5),
            $user,
            $partner,
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
            ovr: 0,
            injured: false,
            currentSalary: 650,
            yearsRemaining: 1,
            futureSalary: 0,
            dcMinutes: 4,
        );
        $user = RosterFactory::team(1, [$dead, RosterFactory::player(11, 'C')]);

        $reason = (new ReasonBuilder())->forSide(
            self::paired([10], []),
            new Judgment(20, 20, 0, 0, 5350, 5650),
            true,
            new NeedsCard(1, [new Need(NeedKind::DeadWeight, 650, null, [10])], 6000, [], TeamMode::Rebuild, 0.5),
            $user,
            RosterFactory::team(2, [], 5000),
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
            $userSide ? self::paired([10], []) : self::paired([], [10]),
            new Judgment(100, 30, 0, 0, $userSide ? 6953 : 5700, $userSide ? 5700 : 6953),
            $userSide,
            $card,
            $over,
            $absorber,
            RosterFactory::emptyRanks(),
        );
    }

    /**
     * @param list<int> $userPids
     * @param list<int> $partnerPids
     */
    private static function paired(array $userPids, array $partnerPids): PairedCandidate
    {
        return new PairedCandidate(
            new TradeCandidate(1, 2, $userPids, [], $partnerPids, []),
            Pairing::SalaryDump,
            new Need(NeedKind::OverHardCap, 1),
            new Need(NeedKind::RoomToAbsorb, 1),
        );
    }
}
