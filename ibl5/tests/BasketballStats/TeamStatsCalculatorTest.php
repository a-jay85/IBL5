<?php

declare(strict_types=1);

namespace Tests\BasketballStats;

use PHPUnit\Framework\TestCase;
use BasketballStats\TeamStatsCalculator;
use League\LeagueContext;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * TeamStatsCalculatorTest - Tests for team statistics calculation
 */
class TeamStatsCalculatorTest extends TestCase
{
    private TeamStatsCalculator $calculator;
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->calculator = new TeamStatsCalculator($this->mockDb);
    }

    /**
     * Run the preload path (fetchAll) and the per-team path (fetchOne) through MockDatabase::prepare(), return both SQL strings.
     *
     * @return list<string>
     */
    private function captureStandingsSql(?LeagueContext $context): array
    {
        $db = new MockDatabase();

        $preloading = new TeamStatsCalculator($db, $context);
        $preloading->preloadTeamRecords();

        // Fresh calculator, no preload: calculate() falls back to the per-team fetchOne lookup.
        $perTeam = new TeamStatsCalculator($db, $context);
        $perTeam->calculate([
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 95],
        ], 1);

        return array_values(array_filter(
            $db->getPreparedQueries(),
            static fn (string $query): bool => str_contains($query, 'standings')
        ));
    }

    /**
     * Prepared statements that hit ibl_standings.
     *
     * @return list<string>
     */
    private function standingsQueries(MockDatabase $db): array
    {
        return array_values(array_filter(
            $db->getPreparedQueries(),
            static fn (string $query): bool => str_contains($query, 'ibl_standings')
        ));
    }

    public function testCalculateInitializesCorrectlyWithEmptyGames(): void
    {
        $games = [];
        $result = $this->calculator->calculate($games, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('wins', $result);
        $this->assertArrayHasKey('losses', $result);
        $this->assertArrayHasKey('homeWins', $result);
        $this->assertArrayHasKey('homeLosses', $result);
        $this->assertArrayHasKey('awayWins', $result);
        $this->assertArrayHasKey('awayLosses', $result);
        $this->assertArrayHasKey('streak', $result);
        $this->assertArrayHasKey('streakType', $result);

        $this->assertSame(0, $result['wins']);
        $this->assertSame(0, $result['losses']);
    }

    public function testCalculateWithAwayWin(): void
    {
        $games = [
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 95]
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(1, $result['wins']);
        $this->assertSame(0, $result['losses']);
        $this->assertSame(1, $result['awayWins']);
        $this->assertSame(0, $result['homeWins']);
        $this->assertSame('W', $result['streakType']);
        $this->assertSame(1, $result['streak']);
    }

    public function testCalculateWithHomeLoss(): void
    {
        $games = [
            ['visitor_teamid' => 2, 'visitor_score' => 100, 'home_teamid' => 1, 'home_score' => 95]
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(0, $result['wins']);
        $this->assertSame(1, $result['losses']);
        $this->assertSame(0, $result['awayLosses']);
        $this->assertSame(1, $result['homeLosses']);
        $this->assertSame('L', $result['streakType']);
        $this->assertSame(1, $result['streak']);
    }

    public function testCalculateWithHomeWin(): void
    {
        $games = [
            ['visitor_teamid' => 2, 'visitor_score' => 95, 'home_teamid' => 1, 'home_score' => 100]
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(1, $result['wins']);
        $this->assertSame(0, $result['losses']);
        $this->assertSame(1, $result['homeWins']);
        $this->assertSame('W', $result['streakType']);
    }

    public function testCalculateWithAwayLoss(): void
    {
        $games = [
            ['visitor_teamid' => 1, 'visitor_score' => 95, 'home_teamid' => 2, 'home_score' => 100]
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(0, $result['wins']);
        $this->assertSame(1, $result['losses']);
        $this->assertSame(1, $result['awayLosses']);
        $this->assertSame('L', $result['streakType']);
    }

    public function testCalculateStreakIncrementsForConsecutiveWins(): void
    {
        $games = [
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 95],
            ['visitor_teamid' => 1, 'visitor_score' => 105, 'home_teamid' => 3, 'home_score' => 100],
            ['visitor_teamid' => 1, 'visitor_score' => 110, 'home_teamid' => 4, 'home_score' => 105],
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(3, $result['wins']);
        $this->assertSame(3, $result['streak']);
        $this->assertSame('W', $result['streakType']);
    }

    public function testCalculateStreakResetsOnLoss(): void
    {
        $games = [
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 95],
            ['visitor_teamid' => 1, 'visitor_score' => 95, 'home_teamid' => 3, 'home_score' => 100],
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(1, $result['wins']);
        $this->assertSame(1, $result['losses']);
        $this->assertSame(1, $result['streak']);
        $this->assertSame('L', $result['streakType']);
    }

    public function testCalculateLast10GamesTracking(): void
    {
        // Create 15 games, team wins all
        $games = [];
        for ($i = 0; $i < 15; $i++) {
            $games[] = ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => $i + 2, 'home_score' => 95];
        }

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(15, $result['wins']);
        $this->assertSame(10, $result['winsInLast10Games']);
        $this->assertSame(0, $result['lossesInLast10Games']);
    }

    public function testCalculateSkipsTiedGames(): void
    {
        $games = [
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 100], // Tied - should skip
            ['visitor_teamid' => 1, 'visitor_score' => 105, 'home_teamid' => 3, 'home_score' => 100], // Win
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(1, $result['wins']);
        $this->assertSame(0, $result['losses']);
    }

    // --- Merged from TeamStatsCalculatorEdgeCaseTest ---

    // ============================================
    // STREAK EDGE CASES
    // ============================================

    public function testLongWinStreak(): void
    {
        // 15 consecutive wins
        $games = [];
        for ($i = 0; $i < 15; $i++) {
            $games[] = ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => $i + 2, 'home_score' => 95];
        }

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(15, $result['streak']);
        $this->assertSame('W', $result['streakType']);
    }

    public function testLongLossStreak(): void
    {
        // 15 consecutive losses
        $games = [];
        for ($i = 0; $i < 15; $i++) {
            $games[] = ['visitor_teamid' => 1, 'visitor_score' => 95, 'home_teamid' => $i + 2, 'home_score' => 100];
        }

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(15, $result['streak']);
        $this->assertSame('L', $result['streakType']);
    }

    public function testAlternatingWinsAndLosses(): void
    {
        // Win, loss, win, loss pattern
        $games = [
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 95], // Win
            ['visitor_teamid' => 1, 'visitor_score' => 95, 'home_teamid' => 3, 'home_score' => 100], // Loss
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 4, 'home_score' => 95], // Win
            ['visitor_teamid' => 1, 'visitor_score' => 95, 'home_teamid' => 5, 'home_score' => 100], // Loss
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(2, $result['wins']);
        $this->assertSame(2, $result['losses']);
        $this->assertSame(1, $result['streak']); // Current streak is 1
        $this->assertSame('L', $result['streakType']); // Last game was loss
    }

    public function testStreakResetsCorrectlyAfterMultipleConsecutive(): void
    {
        $games = [
            // 3 wins
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 95],
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 3, 'home_score' => 95],
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 4, 'home_score' => 95],
            // 1 loss (resets streak to 1)
            ['visitor_teamid' => 1, 'visitor_score' => 95, 'home_teamid' => 5, 'home_score' => 100],
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(3, $result['wins']);
        $this->assertSame(1, $result['losses']);
        $this->assertSame(1, $result['streak']);
        $this->assertSame('L', $result['streakType']);
    }

    // ============================================
    // MISSING/NULL DATA EDGE CASES
    // ============================================

    public function testHandlesMissingScoreFields(): void
    {
        $games = [
            ['visitor_teamid' => 1, 'home_teamid' => 2], // Missing scores
        ];

        $result = $this->calculator->calculate($games, 1);

        // With missing scores, game should be skipped (0 == 0 is tie)
        $this->assertSame(0, $result['wins']);
        $this->assertSame(0, $result['losses']);
    }

    public function testHandlesMissingTeamFields(): void
    {
        $games = [
            ['visitor_score' => 100, 'home_score' => 95], // Missing team IDs (defaults to 0)
        ];

        $result = $this->calculator->calculate($games, 1);

        // When team IDs default to 0 and teamid=1, falls into else branch
        // Treats game as if team 1 is home team
        // homeScore (95) > awayScore (100) is false, so it's a loss
        $this->assertSame(0, $result['wins']);
        $this->assertSame(1, $result['losses']);
    }

    public function testHandlesNullValues(): void
    {
        $games = [
            ['visitor_teamid' => null, 'visitor_score' => null, 'home_teamid' => null, 'home_score' => null],
        ];

        $result = $this->calculator->calculate($games, 1);

        // Should handle gracefully
        $this->assertIsArray($result);
    }

    // ============================================
    // LAST 10 GAMES EDGE CASES
    // ============================================

    public function testLast10GamesWithExactly10Games(): void
    {
        $games = [];
        for ($i = 0; $i < 10; $i++) {
            $games[] = ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => $i + 2, 'home_score' => 95];
        }

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(10, $result['wins']);
        $this->assertSame(10, $result['winsInLast10Games']);
    }

    public function testLast10GamesWithLessThan10Games(): void
    {
        $games = [];
        for ($i = 0; $i < 5; $i++) {
            $games[] = ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => $i + 2, 'home_score' => 95];
        }

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(5, $result['wins']);
        $this->assertSame(5, $result['winsInLast10Games']);
    }

    public function testLast10GamesWithExactly11Games(): void
    {
        $games = [];
        // First game is a loss (outside last 10)
        $games[] = ['visitor_teamid' => 1, 'visitor_score' => 95, 'home_teamid' => 99, 'home_score' => 100];
        // Next 10 are wins
        for ($i = 0; $i < 10; $i++) {
            $games[] = ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => $i + 2, 'home_score' => 95];
        }

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(10, $result['wins']);
        $this->assertSame(1, $result['losses']);
        $this->assertSame(10, $result['winsInLast10Games']);
        $this->assertSame(0, $result['lossesInLast10Games']);
    }

    // ============================================
    // HOME/AWAY SPLIT EDGE CASES
    // ============================================

    public function testAllHomeGames(): void
    {
        $games = [];
        for ($i = 0; $i < 5; $i++) {
            $games[] = ['visitor_teamid' => $i + 2, 'visitor_score' => 95, 'home_teamid' => 1, 'home_score' => 100];
        }

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(5, $result['wins']);
        $this->assertSame(5, $result['homeWins']);
        $this->assertSame(0, $result['awayWins']);
    }

    public function testAllAwayGames(): void
    {
        $games = [];
        for ($i = 0; $i < 5; $i++) {
            $games[] = ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => $i + 2, 'home_score' => 95];
        }

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(5, $result['wins']);
        $this->assertSame(0, $result['homeWins']);
        $this->assertSame(5, $result['awayWins']);
    }

    public function testNonOpponentStatsStableWhenStandingsRowsRegistered(): void
    {
        $db = new MockDatabase();
        $db->onQuery('FROM ibl_standings', [
            ['teamid' => 2, 'win' => 30, 'loss' => 10],
            ['teamid' => 3, 'win' => 20, 'loss' => 20],
        ]);
        $calculator = new TeamStatsCalculator($db);
        $calculator->preloadTeamRecords();

        $games = [
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 95],
            ['visitor_teamid' => 3, 'visitor_score' => 110, 'home_teamid' => 1, 'home_score' => 105],
            ['visitor_teamid' => 4, 'visitor_score' => 90, 'home_teamid' => 1, 'home_score' => 99],
            ['visitor_teamid' => 1, 'visitor_score' => 88, 'home_teamid' => 5, 'home_score' => 92],
            ['visitor_teamid' => 1, 'visitor_score' => 101, 'home_teamid' => 2, 'home_score' => 100],
        ];

        $result = $calculator->calculate($games, 1);

        $this->assertSame(3, $result['wins']);
        $this->assertSame(2, $result['losses']);
        $this->assertSame(1, $result['homeWins']);
        $this->assertSame(1, $result['homeLosses']);
        $this->assertSame(2, $result['awayWins']);
        $this->assertSame(1, $result['awayLosses']);
        $this->assertSame(3, $result['winsInLast10Games']);
        $this->assertSame(2, $result['lossesInLast10Games']);
        $this->assertSame(1, $result['streak']);
        $this->assertSame('W', $result['streakType']);
    }

    // ============================================
    // WIN/LOSS POINTS EDGE CASES
    // ============================================

    public function testWinPointsDefaultToZeroWithMockDb(): void
    {
        // No standings row for the opponent: fetchOne returns null, so the record is 0-0.
        $this->mockDb->onQuery('FROM ibl_standings WHERE teamid', []);
        $games = [
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 95],
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(1, $result['wins']);
        // Opponent has no standings row, so winPoints stays 0.
        $this->assertSame(0, $result['winPoints']);
        $this->assertCount(1, $this->standingsQueries($this->mockDb));
        $this->assertSame([2], $this->mockDb->getLastBoundParams());
    }

    public function testLossPointsDefaultToZeroWithMockDb(): void
    {
        // No standings row for the opponent: fetchOne returns null, so the record is 0-0.
        $this->mockDb->onQuery('FROM ibl_standings WHERE teamid', []);
        $games = [
            ['visitor_teamid' => 1, 'visitor_score' => 95, 'home_teamid' => 2, 'home_score' => 100],
        ];

        $result = $this->calculator->calculate($games, 1);

        $this->assertSame(1, $result['losses']);
        // Opponent has no standings row, so lossPoints stays 0.
        $this->assertSame(0, $result['lossPoints']);
        $this->assertCount(1, $this->standingsQueries($this->mockDb));
        $this->assertSame([2], $this->mockDb->getLastBoundParams());
    }

    public function testPreloadedOpponentRecordsWeightWinAndLossPoints(): void
    {
        $db = new MockDatabase();
        $db->onQuery('teamid AS teamid', [
            ['teamid' => 2, 'win' => 30, 'loss' => 10],
            ['teamid' => 3, 'win' => 20, 'loss' => 20],
            ['teamid' => 4, 'win' => 5, 'loss' => 35],
        ]);
        $calc = new TeamStatsCalculator($db);
        $calc->preloadTeamRecords();

        $games = [
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 95],
            ['visitor_teamid' => 3, 'visitor_score' => 110, 'home_teamid' => 1, 'home_score' => 105],
            ['visitor_teamid' => 4, 'visitor_score' => 90, 'home_teamid' => 1, 'home_score' => 99],
        ];

        $result = $calc->calculate($games, 1);

        $this->assertSame(2, $result['wins']);
        $this->assertSame(1, $result['losses']);
        $this->assertSame(35, $result['winPoints']);
        $this->assertSame(20, $result['lossPoints']);

        $queries = $db->getPreparedQueries();
        $this->assertCount(1, array_filter($queries, static fn (string $q): bool => str_contains($q, 'teamid AS teamid')));
        $this->assertCount(0, array_filter($queries, static fn (string $q): bool => str_contains($q, 'WHERE teamid = ?')));
    }

    public function testPerTeamLookupWeightsPointsWhenNotPreloaded(): void
    {
        $db = new MockDatabase();
        $db->onQuery('FROM ibl_standings WHERE teamid', [['win' => 30, 'loss' => 10]]);
        $calc = new TeamStatsCalculator($db);

        $games = [
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 95],
            ['visitor_teamid' => 2, 'visitor_score' => 100, 'home_teamid' => 1, 'home_score' => 95],
        ];

        $result = $calc->calculate($games, 1);

        $this->assertSame(1, $result['wins']);
        $this->assertSame(1, $result['losses']);
        $this->assertSame(30, $result['winPoints']);
        $this->assertSame(10, $result['lossPoints']);
        $this->assertCount(2, $this->standingsQueries($db));
        $this->assertSame([2], $db->getLastBoundParams());
    }

    public function testOpponentMissingFromPreloadContributesZero(): void
    {
        $db = new MockDatabase();
        $db->onQuery('teamid AS teamid', [['teamid' => 2, 'win' => 30, 'loss' => 10]]);
        $calc = new TeamStatsCalculator($db);
        $calc->preloadTeamRecords();

        $games = [
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 95],
            ['visitor_teamid' => 9, 'visitor_score' => 90, 'home_teamid' => 1, 'home_score' => 99],
        ];

        $result = $calc->calculate($games, 1);

        $this->assertSame(2, $result['wins']);
        $this->assertSame(30, $result['winPoints']);
        $this->assertCount(
            0,
            array_filter($db->getPreparedQueries(), static fn (string $q): bool => str_contains($q, 'WHERE teamid = ?'))
        );
    }

    public function testPreloadTeamRecordsQueriesOnce(): void
    {
        $db = new MockDatabase();
        $db->onQuery('teamid AS teamid', [['teamid' => 2, 'win' => 30, 'loss' => 10]]);
        $calc = new TeamStatsCalculator($db);

        $calc->preloadTeamRecords();
        $calc->preloadTeamRecords();

        $this->assertCount(
            1,
            array_filter($db->getPreparedQueries(), static fn (string $q): bool => str_contains($q, 'teamid AS teamid'))
        );
    }

    public function testStandingsQueriesTargetIblStandingsWithoutLeagueContext(): void
    {
        $sql = $this->captureStandingsSql(null);

        $this->assertCount(2, $sql);
        foreach ($sql as $query) {
            $this->assertMatchesRegularExpression('/FROM ibl_standings(\s|$)/', $query);
            $this->assertStringNotContainsString('ibl_olympics_standings', $query);
        }
    }

    public function testStandingsQueriesTargetOlympicsStandingsInOlympicsContext(): void
    {
        $context = self::createStub(LeagueContext::class);
        $context->method('isOlympics')->willReturn(true);
        $context->method('getTableName')->willReturnCallback(
            static fn (string $t): string => LeagueContext::TABLE_MAP[$t] ?? $t
        );

        $sql = $this->captureStandingsSql($context);

        $this->assertCount(2, $sql);
        foreach ($sql as $query) {
            $this->assertMatchesRegularExpression('/FROM ibl_olympics_standings(\s|$)/', $query);
        }
    }

    public function testStandingsTablesMatchLeagueContextTableMap(): void
    {
        $value = (new \ReflectionClassConstant(TeamStatsCalculator::class, 'STANDINGS_TABLES'))->getValue();

        $this->assertSame(
            ['ibl' => 'ibl_standings', 'olympics' => LeagueContext::TABLE_MAP['ibl_standings']],
            $value
        );
    }
}
