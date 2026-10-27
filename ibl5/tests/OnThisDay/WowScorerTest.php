<?php

declare(strict_types=1);

namespace Tests\OnThisDay;

use OnThisDay\Moment;
use OnThisDay\MomentKind;
use OnThisDay\WowScorer;
use PHPUnit\Framework\TestCase;

class WowScorerTest extends TestCase
{
    private WowScorer $scorer;

    protected function setUp(): void
    {
        $this->scorer = new WowScorer();
    }

    /**
     * @param array<string, int> $facts
     */
    private function makeMoment(
        MomentKind $kind,
        array $facts = [],
        string $key = '',
        int $seasonYear = 2000,
    ): Moment {
        return new Moment(
            kind: $kind,
            key: $key !== '' ? $key : $kind->value . ':2000-01-01:1',
            date: '2000-01-01',
            seasonYear: $seasonYear,
            pid: 1,
            playerName: 'Test Player',
            teamId: 1,
            teamName: 'Team A',
            opponentTeamId: 2,
            opponentName: 'Team B',
            facts: $facts,
        );
    }

    public function testFiftyPointGameScoresAboveThreshold(): void
    {
        $at50 = $this->makeMoment(MomentKind::FiftyPointGame, ['points' => 50]);
        $at55 = $this->makeMoment(MomentKind::FiftyPointGame, ['points' => 55]);

        $this->assertSame(WowScorer::FIFTY_POINT_BASE, $this->scorer->score($at50));
        $this->assertSame(
            WowScorer::FIFTY_POINT_BASE + 5 * WowScorer::FIFTY_POINT_PER_EXTRA_POINT,
            $this->scorer->score($at55)
        );
    }

    public function testFortyNinePointGameScoresZero(): void
    {
        $m = $this->makeMoment(MomentKind::FiftyPointGame, ['points' => 49]);

        $this->assertSame(0, $this->scorer->score($m));
    }

    public function testTripleDoubleRequiresTenInThreeCategories(): void
    {
        $nineInThird = $this->makeMoment(
            MomentKind::TripleDouble,
            ['points' => 20, 'rebounds' => 10, 'assists' => 9, 'steals' => 0, 'blocks' => 0]
        );
        $exact = $this->makeMoment(
            MomentKind::TripleDouble,
            ['points' => 20, 'rebounds' => 10, 'assists' => 10, 'steals' => 0, 'blocks' => 0]
        );

        $this->assertSame(0, $this->scorer->score($nineInThird));
        // 60 + (20 + 10 + 10 - 30)
        $this->assertSame(WowScorer::TRIPLE_DOUBLE_BASE + 10, $this->scorer->score($exact));
    }

    public function testQuadrupleDoubleGetsBonus(): void
    {
        $triple = $this->makeMoment(
            MomentKind::TripleDouble,
            ['points' => 20, 'rebounds' => 10, 'assists' => 10, 'steals' => 5, 'blocks' => 0]
        );
        $quadruple = $this->makeMoment(
            MomentKind::TripleDouble,
            ['points' => 20, 'rebounds' => 10, 'assists' => 10, 'steals' => 10, 'blocks' => 0]
        );

        $this->assertSame(
            $this->scorer->score($triple) + WowScorer::QUADRUPLE_DOUBLE_BONUS,
            $this->scorer->score($quadruple)
        );
    }

    public function testRecordGameOutranksFiftyPointGame(): void
    {
        $record = $this->makeMoment(MomentKind::RecordGame, ['value' => 61]);
        $fifty = $this->makeMoment(MomentKind::FiftyPointGame, ['points' => 55]);

        $this->assertSame(WowScorer::RECORD_GAME_SCORE, $this->scorer->score($record));
        $this->assertGreaterThan($this->scorer->score($fifty), $this->scorer->score($record));
    }

    public function testSeriesClincherFinalsGameSevenBonus(): void
    {
        $plain = $this->makeMoment(MomentKind::SeriesClincher, ['round' => 1, 'wins' => 4, 'losses' => 1]);
        $finals = $this->makeMoment(MomentKind::SeriesClincher, ['round' => 4, 'wins' => 4, 'losses' => 1]);
        $gameSeven = $this->makeMoment(MomentKind::SeriesClincher, ['round' => 1, 'wins' => 4, 'losses' => 3]);
        $finalsGameSeven = $this->makeMoment(MomentKind::SeriesClincher, ['round' => 4, 'wins' => 4, 'losses' => 3]);

        $this->assertSame(WowScorer::SERIES_CLINCHER_BASE, $this->scorer->score($plain));
        $this->assertSame(
            WowScorer::SERIES_CLINCHER_BASE + WowScorer::FINALS_BONUS,
            $this->scorer->score($finals)
        );
        $this->assertSame(
            WowScorer::SERIES_CLINCHER_BASE + WowScorer::GAME_SEVEN_BONUS,
            $this->scorer->score($gameSeven)
        );
        $this->assertSame(
            WowScorer::SERIES_CLINCHER_BASE + WowScorer::FINALS_BONUS + WowScorer::GAME_SEVEN_BONUS,
            $this->scorer->score($finalsGameSeven)
        );
    }

    public function testTradeScoreCapsPlayersMoved(): void
    {
        $two = $this->makeMoment(MomentKind::Trade, ['players_moved' => 2]);
        $six = $this->makeMoment(MomentKind::Trade, ['players_moved' => 6]);
        $ten = $this->makeMoment(MomentKind::Trade, ['players_moved' => 10]);

        $this->assertSame(WowScorer::TRADE_BASE + 2 * WowScorer::TRADE_PER_PLAYER, $this->scorer->score($two));
        $this->assertSame(
            WowScorer::TRADE_BASE + WowScorer::TRADE_MAX_PLAYERS_COUNTED * WowScorer::TRADE_PER_PLAYER,
            $this->scorer->score($six)
        );
        $this->assertSame($this->scorer->score($six), $this->scorer->score($ten));
    }

    public function testRankTieBreakIsDeterministic(): void
    {
        // Same kind and score; differ only by seasonYear and key.
        $newer = $this->makeMoment(MomentKind::FiftyPointGame, ['points' => 52], 'fifty_point:2010-01-01:7', 2010);
        $olderA = $this->makeMoment(MomentKind::FiftyPointGame, ['points' => 52], 'fifty_point:2001-01-01:1', 2001);
        $olderB = $this->makeMoment(MomentKind::FiftyPointGame, ['points' => 52], 'fifty_point:2001-01-01:2', 2001);

        // MAX_PER_KIND keeps the first two of the total order: seasonYear DESC, then key ASC.
        $expected = [$newer->key, $olderA->key];
        $input = [$newer, $olderA, $olderB];

        $orderings = [
            [0, 1, 2], [0, 2, 1], [1, 0, 2], [1, 2, 0], [2, 0, 1], [2, 1, 0],
        ];
        foreach ($orderings as $order) {
            $shuffled = [$input[$order[0]], $input[$order[1]], $input[$order[2]]];
            $ranked = $this->scorer->rank($shuffled, 10, []);

            $this->assertSame($expected, array_map(static fn (Moment $m): string => $m->key, $ranked));
        }
    }

    public function testRankDedupesPhantomDuplicateKeys(): void
    {
        $a = $this->makeMoment(MomentKind::FiftyPointGame, ['points' => 60], 'fifty_point:2005-01-01:9', 2005);
        $dup = $this->makeMoment(MomentKind::FiftyPointGame, ['points' => 60], 'fifty_point:2005-01-01:9', 2005);

        $ranked = $this->scorer->rank([$a, $dup], 10, []);

        $this->assertCount(1, $ranked);
        $this->assertSame($a, $ranked[0]);
    }

    public function testRankExcludesPreviousSelectionKeys(): void
    {
        $kept = $this->makeMoment(MomentKind::FiftyPointGame, ['points' => 60], 'fifty_point:2005-01-01:1', 2005);
        $dropped = $this->makeMoment(MomentKind::FiftyPointGame, ['points' => 70], 'fifty_point:2006-01-01:2', 2006);

        $ranked = $this->scorer->rank([$kept, $dropped], 10, [$dropped->key]);

        $this->assertSame([$kept->key], array_map(static fn (Moment $m): string => $m->key, $ranked));
    }

    public function testRankCapsPerKindForVariety(): void
    {
        $fifties = [];
        for ($i = 0; $i < 4; $i++) {
            $fifties[] = $this->makeMoment(
                MomentKind::FiftyPointGame,
                ['points' => 80 - $i],
                'fifty_point:200' . $i . '-01-01:' . $i,
                2000 + $i
            );
        }
        $trade = $this->makeMoment(MomentKind::Trade, ['players_moved' => 1], 'trade:2003-01-01:1-2', 2003);

        $ranked = $this->scorer->rank([...$fifties, $trade], 10, []);

        $fiftyCount = count(array_filter(
            $ranked,
            static fn (Moment $m): bool => $m->kind === MomentKind::FiftyPointGame
        ));
        $this->assertSame(WowScorer::MAX_PER_KIND, $fiftyCount);
        $this->assertCount(WowScorer::MAX_PER_KIND + 1, $ranked);
        $this->assertSame(MomentKind::Trade, $ranked[WowScorer::MAX_PER_KIND]->kind);
    }

    public function testRankReturnsAtMostLimit(): void
    {
        $moments = [
            $this->makeMoment(MomentKind::FiftyPointGame, ['points' => 60], 'fifty_point:2001-01-01:1', 2001),
            $this->makeMoment(MomentKind::RecordGame, ['value' => 10], 'record_game:2002-01-01:2', 2002),
            $this->makeMoment(MomentKind::Trade, ['players_moved' => 3], 'trade:2003-01-01:3-4', 2003),
            $this->makeMoment(MomentKind::SeriesClincher, ['round' => 1, 'wins' => 4, 'losses' => 0], 'series_clincher:2004-01-01:5-6', 2004),
        ];

        $this->assertCount(2, $this->scorer->rank($moments, 2, []));
        $this->assertSame([], $this->scorer->rank($moments, 0, []));
    }
}
