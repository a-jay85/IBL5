<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\HorizonScores;
use TradeSuggestions\SuggestionPick;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamAssets;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TeamNeedsScorer;

class TeamNeedsScorerTest extends TestCase
{
    private TeamNeedsScorer $scorer;

    protected function setUp(): void
    {
        $this->scorer = new TeamNeedsScorer();
    }

    public function testNowSumsHealthyRotationOnly(): void
    {
        $players = [
            self::player(1, 'C', 25, injured: true),
            self::player(2, 'PG', 20),
            self::player(3, 'SG', 19),
            self::player(4, 'G', 18),
            self::player(5, 'SF', 17),
            self::player(6, 'GF', 16),
            self::player(7, 'F', 15),
            self::player(8, 'PF', 14),
            self::player(9, 'C', 13),
        ];

        $scores = $this->scorer->score(new TeamAssets(1, $players, []));

        $this->assertSame(20.0 + 19 + 18 + 17 + 16 + 15 + 14 + 13, $scores->now);
        $this->assertSame([], $scores->positionHoles);
    }

    public function testPositionHolePenalised(): void
    {
        $players = [
            self::player(1, 'PG', 20),
            self::player(2, 'SG', 20),
            self::player(3, 'G', 20),
            self::player(4, 'PG', 20),
            self::player(5, 'SF', 20),
            self::player(6, 'GF', 20),
            self::player(7, 'F', 20),
            self::player(8, 'C', 20),
        ];

        $scores = $this->scorer->score(new TeamAssets(1, $players, []));

        $this->assertSame(['big' => 1], $scores->positionHoles);
        $this->assertSame(160.0 - 6.0, $scores->now);
    }

    public function testShortDiscountsExpiringAndAging(): void
    {
        $veteran = self::player(1, 'C', 20, age: 31, yearsRemaining: 1);
        $signed = self::player(2, 'PG', 10, age: 25, yearsRemaining: 2);

        $scores = $this->scorer->score(new TeamAssets(1, [$veteran, $signed], []));

        $this->assertEqualsWithDelta(20 * 0.5 * 0.6 + 10.0, $scores->short, 1e-9);
    }

    public function testLongRewardsYouthPicksAndCapRoom(): void
    {
        $young = self::player(1, 'SF', 17, age: 22, futureSalary: 15000);
        $pick = new SuggestionPick(100, 1, 2027, 1, 5.0);

        $scores = $this->scorer->score(new TeamAssets(1, [$young], [$pick]));

        $this->assertEqualsWithDelta(17.0 + 5.0 + 5.0, $scores->long, 1e-9);
    }

    public function testWeightedFollowsMode(): void
    {
        $scores = new HorizonScores(10.0, 20.0, 40.0, []);

        $this->assertEqualsWithDelta(6.0 + 6.0 + 4.0, $scores->weighted(TeamMode::WinNow), 1e-9);
        $this->assertEqualsWithDelta(1.0 + 6.0 + 24.0, $scores->weighted(TeamMode::Rebuild), 1e-9);
    }

    public function testEmptyRosterScoresZeroWithFullHoles(): void
    {
        $scores = $this->scorer->score(new TeamAssets(1, [], []));

        $this->assertSame(-36.0, $scores->now);
        $this->assertSame(0.0, $scores->short);
        $this->assertEqualsWithDelta(20.0, $scores->long, 1e-9);
        $this->assertSame(['guard' => 2, 'wing' => 2, 'big' => 2], $scores->positionHoles);
    }

    private static function player(
        int $pid,
        string $pos,
        int $quality,
        int $age = 27,
        bool $injured = false,
        int $yearsRemaining = 3,
        int $futureSalary = 0,
    ): SuggestionPlayer {
        return new SuggestionPlayer($pid, 'Player ' . $pid, $pos, $age, $quality, $injured, 1000, $yearsRemaining, $futureSalary);
    }
}
