<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\ReasonBuilder;
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

        self::assertContains('Frees $3,200K of salary owed after this season.', $options);
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
}
