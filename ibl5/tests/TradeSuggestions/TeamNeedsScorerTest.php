<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\AssetValuer;
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

    public function testTopSlotsWeighMoreThanBench(): void
    {
        // Two guards, two wings, two bigs: no holes. Values 10 each (ovr 55).
        $players = [];
        foreach (['PG', 'SG', 'SF', 'GF', 'PF', 'C', 'C', 'C', 'C', 'C', 'C'] as $i => $pos) {
            $players[] = self::player($i + 1, $pos, 55);
        }

        $score = $this->scorer->score(new TeamAssets(1, $players, []), TeamMode::WinNow);

        // 5 x 1.0 + 3 x 0.6 + 2 x 0.25 + 1 x 0.1, times 10.
        self::assertEqualsWithDelta(10 * (5 + 1.8 + 0.5 + 0.1), $score->total, 1e-9);
        self::assertSame([], $score->positionHoles);
    }

    public function testPositionHoleCountsTopEightOnlyAndCostsMoreWhenWinningNow(): void
    {
        $players = [];
        foreach (['PG', 'SG', 'G', 'PG', 'SF', 'GF', 'F', 'C'] as $i => $pos) {
            $players[] = self::player($i + 1, $pos, 60);
        }
        // A ninth big sits outside the top eight, so the big-man hole stays.
        $players[] = self::player(9, 'C', 50);
        $assets = new TeamAssets(1, $players, []);

        $winNow = $this->scorer->score($assets, TeamMode::WinNow);
        $rebuild = $this->scorer->score($assets, TeamMode::Rebuild);

        self::assertSame(['big' => 1], $winNow->positionHoles);
        self::assertSame(1, $winNow->groupCounts['big']);
        self::assertSame(4, $winNow->groupCounts['guard']);
        $winNowValues = 22.5 * (5 + 3 * 0.6) + 2.5 * 0.25;
        self::assertEqualsWithDelta($winNowValues - TeamNeedsScorer::WIN_NOW_HOLE_PENALTY, $winNow->total, 1e-9);
        self::assertSame(['big' => 1], $rebuild->positionHoles);
    }

    public function testPicksAddTheirModeValue(): void
    {
        $pick = new SuggestionPick(100, 1, 2027, 1, 10.0);

        $winNow = $this->scorer->score(new TeamAssets(1, [], [$pick]), TeamMode::WinNow);
        $rebuild = $this->scorer->score(new TeamAssets(1, [], [$pick]), TeamMode::Rebuild);

        $holes = 3 * TeamNeedsScorer::MIN_PER_GROUP;
        self::assertEqualsWithDelta(10 * AssetValuer::WIN_NOW_PICK_FACTOR - $holes * TeamNeedsScorer::WIN_NOW_HOLE_PENALTY, $winNow->total, 1e-9);
        self::assertEqualsWithDelta(10 * AssetValuer::REBUILD_PICK_FACTOR - $holes * TeamNeedsScorer::REBUILD_HOLE_PENALTY, $rebuild->total, 1e-9);
    }

    public function testPositionGroupMapsEveryPosition(): void
    {
        self::assertSame('guard', TeamNeedsScorer::positionGroup('G'));
        self::assertSame('wing', TeamNeedsScorer::positionGroup('GF'));
        self::assertSame('big', TeamNeedsScorer::positionGroup('PF'));
        self::assertNull(TeamNeedsScorer::positionGroup(''));
    }

    private static function player(int $pid, string $pos, int $ovr): SuggestionPlayer
    {
        return new SuggestionPlayer($pid, 'Player ' . $pid, $pos, 27, $ovr, false, 1000, 3, 2000);
    }
}
