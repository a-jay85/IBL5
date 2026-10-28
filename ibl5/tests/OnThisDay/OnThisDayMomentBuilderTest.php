<?php

declare(strict_types=1);

namespace Tests\OnThisDay;

use OnThisDay\Moment;
use OnThisDay\MomentKind;
use OnThisDay\OnThisDayMomentBuilder;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Row-to-moment mapping and the rebuild write path. The SQL itself runs
 * against a real schema in Tests\DatabaseIntegration\OnThisDayPipelineTest.
 *
 * @covers \OnThisDay\OnThisDayMomentBuilder
 */
final class OnThisDayMomentBuilderTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        // No regular-season box scores, so the record stream never opens.
        $this->mockDb->onQuery('first_season', []);
        $this->mockDb->onQuery('g\.pts >= 50', []);
        $this->mockDb->onQuery('g\.ast >= 10', []);
        $this->mockDb->onQuery('ibl_playoff_series_results', []);
        $this->mockDb->onQuery('WITH games AS', []);
    }

    /**
     * @return list<Moment>
     */
    private function collect(): array
    {
        return (new OnThisDayMomentBuilder($this->mockDb))->collect();
    }

    public function testCollectReturnsNothingWhenEverySourceIsEmpty(): void
    {
        $this->assertSame([], $this->collect());
    }

    public function testFiftyPointGameRowBecomesMoment(): void
    {
        $this->mockDb->onQuery('g\.pts >= 50', [[
            'game_date' => '1999-03-14',
            'pid' => 12,
            'season_year' => 1999,
            'player_name' => 'Big Scorer',
            'teamid' => 5,
            'team_name' => 'Home City Hawks',
            'opponent_teamid' => 6,
            'opponent_name' => 'Away City Owls',
            'pts' => 55,
            'reb' => 9,
            'ast' => 4,
            'stl' => 2,
            'blk' => 1,
        ]]);

        $moments = $this->collect();

        $this->assertCount(1, $moments);
        $moment = $moments[0];
        $this->assertSame(MomentKind::FiftyPointGame, $moment->kind);
        $this->assertSame('fifty_point:1999-03-14:12', $moment->key);
        $this->assertSame('1999-03-14', $moment->date);
        $this->assertSame(1999, $moment->seasonYear);
        $this->assertSame(12, $moment->pid);
        $this->assertSame('Big Scorer', $moment->playerName);
        $this->assertSame(5, $moment->teamId);
        $this->assertSame('Home City Hawks', $moment->teamName);
        $this->assertSame(6, $moment->opponentTeamId);
        $this->assertSame('Away City Owls', $moment->opponentName);
        $this->assertSame(
            ['points' => 55, 'rebounds' => 9, 'assists' => 4, 'steals' => 2, 'blocks' => 1],
            $moment->facts,
        );
        $this->assertNull($moment->statLabel);
    }

    public function testTripleDoubleRowCastsNumericStringsAndDefaultsMissingColumns(): void
    {
        $this->mockDb->onQuery('g\.ast >= 10', [[
            'game_date' => '2004-11-02',
            'pid' => '33',
            'season_year' => '2005',
            'player_name' => 'All Around',
            'teamid' => '8',
            'opponent_teamid' => '9',
            'opponent_name' => null,
            'pts' => '12',
            'reb' => '11',
            'ast' => '10',
            'stl' => 'n/a',
            'blk' => '0',
        ]]);

        $moments = $this->collect();

        $this->assertCount(1, $moments);
        $moment = $moments[0];
        $this->assertSame(MomentKind::TripleDouble, $moment->kind);
        $this->assertSame('triple_double:2004-11-02:33', $moment->key);
        $this->assertSame(2005, $moment->seasonYear);
        $this->assertSame(33, $moment->pid);
        $this->assertSame(8, $moment->teamId);
        $this->assertSame('', $moment->teamName);
        $this->assertSame(9, $moment->opponentTeamId);
        $this->assertSame('', $moment->opponentName);
        $this->assertSame(
            ['points' => 12, 'rebounds' => 11, 'assists' => 10, 'steals' => 0, 'blocks' => 0],
            $moment->facts,
        );
    }

    public function testSeriesClincherRowBecomesTeamMoment(): void
    {
        $this->mockDb->onQuery('ibl_playoff_series_results', [[
            'year' => 2010,
            'round' => 3,
            'winner_tid' => 2,
            'loser_tid' => 7,
            'winner_games' => 4,
            'loser_games' => 2,
            'clinch_date' => '2010-05-20',
            'winner_name' => 'Winner Town Wolves',
            'loser_name' => 'Loser Town Lynx',
        ]]);

        $moments = $this->collect();

        $this->assertCount(1, $moments);
        $moment = $moments[0];
        $this->assertSame(MomentKind::SeriesClincher, $moment->kind);
        $this->assertSame('series_clincher:2010-05-20:2-7', $moment->key);
        $this->assertSame('2010-05-20', $moment->date);
        $this->assertSame(2010, $moment->seasonYear);
        $this->assertSame(0, $moment->pid);
        $this->assertSame('', $moment->playerName);
        $this->assertSame(2, $moment->teamId);
        $this->assertSame('Winner Town Wolves', $moment->teamName);
        $this->assertSame(7, $moment->opponentTeamId);
        $this->assertSame('Loser Town Lynx', $moment->opponentName);
        $this->assertSame(['round' => 3, 'wins' => 4, 'losses' => 2], $moment->facts);
    }

    public function testTradeRowBecomesMoveMoment(): void
    {
        $this->mockDb->onQuery('WITH games AS', [[
            'pid' => 44,
            'season_year' => 2012,
            'debut_date' => '2012-01-15',
            'from_tid' => 10,
            'to_tid' => 11,
            'players_moved' => 2,
            'player_name' => 'Moved Scorer',
            'to_name' => 'New Home Hornets',
            'from_name' => 'Old Home Otters',
        ]]);

        $moments = $this->collect();

        $this->assertCount(1, $moments);
        $moment = $moments[0];
        $this->assertSame(MomentKind::Trade, $moment->kind);
        $this->assertSame('trade:2012-01-15:10-11', $moment->key);
        $this->assertSame('2012-01-15', $moment->date);
        $this->assertSame(2012, $moment->seasonYear);
        $this->assertSame(0, $moment->pid);
        $this->assertSame('Moved Scorer', $moment->playerName);
        $this->assertSame(11, $moment->teamId);
        $this->assertSame('New Home Hornets', $moment->teamName);
        $this->assertSame(10, $moment->opponentTeamId);
        $this->assertSame('Old Home Otters', $moment->opponentName);
        $this->assertSame(['players_moved' => 2], $moment->facts);
    }

    public function testTradeQueryBindsMinimumScoringAndGames(): void
    {
        $this->collect();

        $tradeQueries = array_values(array_filter(
            $this->mockDb->getExecutedQueries(),
            static fn (string $query): bool => str_contains($query, 'WITH games AS'),
        ));
        $this->assertCount(1, $tradeQueries);
        $this->assertStringContainsString('HAVING AVG(old.pts) >= 15 AND COUNT(*) >= 5', $tradeQueries[0]);
    }

    public function testCollectOrdersKindsFiftyTripleClincherTrade(): void
    {
        $this->mockDb->onQuery('g\.pts >= 50', [['game_date' => '2000-01-01', 'pid' => 1]]);
        $this->mockDb->onQuery('g\.ast >= 10', [['game_date' => '2000-01-02', 'pid' => 2]]);
        $this->mockDb->onQuery('ibl_playoff_series_results', [['clinch_date' => '2000-05-01']]);
        $this->mockDb->onQuery('WITH games AS', [['debut_date' => '2000-02-01']]);

        $kinds = array_map(static fn (Moment $m): MomentKind => $m->kind, $this->collect());

        $this->assertSame(
            [MomentKind::FiftyPointGame, MomentKind::TripleDouble, MomentKind::SeriesClincher, MomentKind::Trade],
            $kinds,
        );
    }

    public function testRebuildReplacesRowsInOneTransactionAndReturnsCount(): void
    {
        $this->mockDb->onQuery('ibl_playoff_series_results', [[
            'year' => 2010,
            'round' => 1,
            'winner_tid' => 2,
            'loser_tid' => 7,
            'winner_games' => 4,
            'loser_games' => 0,
            'clinch_date' => '2010-04-28',
            'winner_name' => 'Winner Town Wolves',
            'loser_name' => 'Loser Town Lynx',
        ]]);

        $count = (new OnThisDayMomentBuilder($this->mockDb))->rebuild();

        $this->assertSame(1, $count);

        $log = $this->mockDb->getOperationLog();
        $begin = array_search('BEGIN', $log, true);
        $commit = array_search('COMMIT', $log, true);
        $delete = $this->firstIndexContaining($log, 'DELETE FROM ibl_on_this_day_moments');
        $insert = $this->firstIndexContaining($log, 'INSERT INTO ibl_on_this_day_moments');
        $this->assertIsInt($begin);
        $this->assertIsInt($commit);
        $this->assertNotNull($delete);
        $this->assertNotNull($insert);
        $this->assertTrue($begin < $delete && $delete < $insert && $insert < $commit);

        $this->assertSame('ssssiisisisss', $this->mockDb->getLastBoundTypes());
        $this->assertSame(
            [
                'series_clincher:2010-04-28:2-7',
                'series_clincher',
                '2010-04-28',
                '04-28',
                2010,
                0,
                '',
                2,
                'Winner Town Wolves',
                7,
                'Loser Town Lynx',
                '{"round":1,"wins":4,"losses":0}',
                null,
            ],
            $this->mockDb->getLastBoundParams(),
        );
    }

    /**
     * @param list<string> $log
     */
    private function firstIndexContaining(array $log, string $needle): ?int
    {
        foreach ($log as $index => $entry) {
            if (str_contains($entry, $needle)) {
                return $index;
            }
        }

        return null;
    }
}
