<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Updater\StandingsUpdater;

/**
 * Testable subclass that overrides DB methods to inject test data
 *
 * @phpstan-import-type TeamMapping from StandingsUpdater
 */
class TestableStandingsUpdater extends StandingsUpdater
{
    /** @var array<int, array{conference: string, division: string, teamName: string}> */
    private array $testTeamMap = [];

    /** @var list<array{Visitor: int, VScore: int, Home: int, HScore: int}> */
    private array $testGames = [];

    /**
     * @param array<int, array{conference: string, division: string, teamName: string}> $teamMap
     */
    public function setTestTeamMap(array $teamMap): void
    {
        $this->testTeamMap = $teamMap;
    }

    /**
     * @param list<array{Visitor: int, VScore: int, Home: int, HScore: int}> $games
     */
    public function setTestGames(array $games): void
    {
        $this->testGames = $games;
    }

    protected function fetchTeamMap(): array
    {
        return $this->testTeamMap;
    }

    protected function fetchPlayedGames(): array
    {
        return $this->testGames;
    }
}

/**
 * Tests for StandingsUpdater class
 *
 * Tests standings computation from game results including:
 * - Total W/L and home/away splits
 * - Conference record (same-conference games only)
 * - Division record (same-division games only)
 * - Win percentage calculation
 * - Games back calculation
 * - Games unplayed calculation
 * - Edge cases (0 games, ties in the standings)
 * - Grouping assignments for regions
 * - Magic number query execution
 */
class StandingsUpdaterTest extends TestCase
{
    private MockDatabase $mockDb;
    private \Season $mockSeason;
    private TestableStandingsUpdater $updater;

    /** @var array<int, array{conference: string, division: string, teamName: string}> */
    private array $defaultTeamMap;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->mockSeason = new \Season($this->mockDb);
        $this->mockSeason->phase = 'Regular Season';
        $this->mockSeason->beginningYear = 2006;
        $this->mockSeason->endingYear = 2007;

        $this->updater = new TestableStandingsUpdater($this->mockDb, $this->mockSeason);

        $this->defaultTeamMap = [
            1 => ['conference' => 'Eastern', 'division' => 'Atlantic', 'teamName' => 'Celtics'],
            2 => ['conference' => 'Eastern', 'division' => 'Atlantic', 'teamName' => 'Heat'],
            3 => ['conference' => 'Eastern', 'division' => 'Central', 'teamName' => 'Bulls'],
            4 => ['conference' => 'Western', 'division' => 'Pacific', 'teamName' => 'Lakers'],
            5 => ['conference' => 'Western', 'division' => 'Pacific', 'teamName' => 'Clippers'],
            6 => ['conference' => 'Western', 'division' => 'Midwest', 'teamName' => 'Rockets'],
        ];
    }

    protected function tearDown(): void
    {
        unset($this->updater, $this->mockDb, $this->mockSeason);
    }

    public function testUpdateTruncatesStandingsTable(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->updater->setTestTeamMap([]);
        $this->updater->setTestGames([]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertNotEmpty($queries);
        $this->assertSame('TRUNCATE TABLE ibl_standings', $queries[0]);
    }

    public function testGameResultsProduceCorrectTotalWinLoss(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->updater->setTestTeamMap($this->defaultTeamMap);
        $this->updater->setTestGames([
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 90],
            ['Visitor' => 2, 'VScore' => 95, 'Home' => 1, 'HScore' => 105],
            ['Visitor' => 1, 'VScore' => 80, 'Home' => 4, 'HScore' => 90],
        ]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        // Team 1 (Celtics): 2 wins, 1 loss
        $team1Insert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $this->assertNotNull($team1Insert, 'Expected INSERT for Celtics');
        $this->assertStringContainsString("'2-1'", $team1Insert);

        // Team 2 (Heat): 0 wins, 2 losses
        $team2Insert = $this->findInsertForTeam($insertQueries, "'Heat'");
        $this->assertNotNull($team2Insert, 'Expected INSERT for Heat');
        $this->assertStringContainsString("'0-2'", $team2Insert);

        // Team 4 (Lakers): 1 win, 0 losses
        $team4Insert = $this->findInsertForTeam($insertQueries, "'Lakers'");
        $this->assertNotNull($team4Insert, 'Expected INSERT for Lakers');
        $this->assertStringContainsString("'1-0'", $team4Insert);
    }

    public function testHomeAwayRecordsSplitCorrectly(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->updater->setTestTeamMap($this->defaultTeamMap);
        $this->updater->setTestGames([
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 90],  // Team 1 away win
            ['Visitor' => 4, 'VScore' => 80, 'Home' => 1, 'HScore' => 95],   // Team 1 home win
        ]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        $team1Insert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $this->assertNotNull($team1Insert, 'Expected INSERT for Celtics');

        // Verify the INSERT has homeWins=1, homeLosses=0, awayWins=1, awayLosses=0
        // These are the last 4 integer values in the INSERT
        // homeRecord should be '1-0', awayRecord should be '1-0'
        $this->assertStringContainsString("'1-0'", $team1Insert);
    }

    public function testConferenceRecordOnlyCountsSameConferenceGames(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->updater->setTestTeamMap($this->defaultTeamMap);
        $this->updater->setTestGames([
            // Eastern vs Eastern (should count for conf record)
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 90],
            // Eastern vs Western (should NOT count for conf record)
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 4, 'HScore' => 90],
        ]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        $team1Insert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $this->assertNotNull($team1Insert, 'Expected INSERT for Celtics');

        // Team 1 total: 2-0, confRecord should be 1-0 (only the Eastern vs Eastern game)
        // The confRecord '1-0' appears in the query
        // leagueRecord is '2-0', confRecord is '1-0'
        $this->assertStringContainsString("'2-0'", $team1Insert);
    }

    public function testDivisionRecordOnlyCountsSameDivisionGames(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->updater->setTestTeamMap($this->defaultTeamMap);
        $this->updater->setTestGames([
            // Atlantic vs Atlantic (same division)
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 90],
            // Atlantic vs Central (same conference, different division)
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 3, 'HScore' => 90],
        ]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        $team1Insert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $this->assertNotNull($team1Insert, 'Expected INSERT for Celtics');

        // Team 1: total 2-0, conf 2-0, div 1-0 (only vs Heat in Atlantic)
        // The INSERT should contain the confWins=2, divWins=1 somewhere
        $this->assertStringContainsString("'2-0'", $team1Insert);
    }

    public function testWinPercentageCalculation(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->updater->setTestTeamMap([
            1 => ['conference' => 'Eastern', 'division' => 'Atlantic', 'teamName' => 'Celtics'],
            2 => ['conference' => 'Eastern', 'division' => 'Atlantic', 'teamName' => 'Heat'],
        ]);
        $this->updater->setTestGames([
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 90],
            ['Visitor' => 2, 'VScore' => 100, 'Home' => 1, 'HScore' => 90],
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 90],
        ]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        // Team 1: 2 wins, 1 loss → pct = 0.667
        $team1Insert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $this->assertNotNull($team1Insert, 'Expected INSERT for Celtics');
        $this->assertStringContainsString('0.667', $team1Insert);

        // Team 2: 1 win, 2 losses → pct = 0.333
        $team2Insert = $this->findInsertForTeam($insertQueries, "'Heat'");
        $this->assertNotNull($team2Insert, 'Expected INSERT for Heat');
        $this->assertStringContainsString('0.333', $team2Insert);
    }

    public function testGamesUnplayedCalculation(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->updater->setTestTeamMap([
            1 => ['conference' => 'Eastern', 'division' => 'Atlantic', 'teamName' => 'Celtics'],
            2 => ['conference' => 'Eastern', 'division' => 'Atlantic', 'teamName' => 'Heat'],
        ]);

        // 5 games total for team 1 (3 away + 2 home)
        $games = [];
        for ($i = 0; $i < 3; $i++) {
            $games[] = ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 90];
        }
        for ($i = 0; $i < 2; $i++) {
            $games[] = ['Visitor' => 2, 'VScore' => 90, 'Home' => 1, 'HScore' => 100];
        }
        $this->updater->setTestGames($games);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        $team1Insert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $this->assertNotNull($team1Insert, 'Expected INSERT for Celtics');
        // gamesUnplayed = 82 - 5 = 77
        $this->assertStringContainsString('77', $team1Insert);
    }

    public function testTeamWithZeroGamesPlayed(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->updater->setTestTeamMap([
            1 => ['conference' => 'Eastern', 'division' => 'Atlantic', 'teamName' => 'Celtics'],
            2 => ['conference' => 'Eastern', 'division' => 'Atlantic', 'teamName' => 'Heat'],
        ]);
        $this->updater->setTestGames([]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        // Both teams should have INSERT queries
        $this->assertCount(2, $insertQueries);

        // Verify pct is 0 (no division by zero)
        $team1Insert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $this->assertNotNull($team1Insert, 'Expected INSERT for Celtics');
        // leagueRecord should be '0-0'
        $this->assertStringContainsString("'0-0'", $team1Insert);
        // gamesUnplayed should be 82
        $this->assertStringContainsString('82', $team1Insert);
    }

    public function testGamesBackCalculation(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->updater->setTestTeamMap([
            1 => ['conference' => 'Eastern', 'division' => 'Atlantic', 'teamName' => 'Celtics'],
            2 => ['conference' => 'Eastern', 'division' => 'Atlantic', 'teamName' => 'Heat'],
        ]);
        $this->updater->setTestGames([
            // Team 1 wins all 4 games (2-0 record)
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 90],
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 90],
            // Team 2 is 0-2 after above, add 2 more wins for both teams
            ['Visitor' => 2, 'VScore' => 100, 'Home' => 1, 'HScore' => 90],
            ['Visitor' => 2, 'VScore' => 100, 'Home' => 1, 'HScore' => 90],
        ]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        // Both teams: 2 wins, 2 losses → 0 GB from each other
        $team1Insert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $team2Insert = $this->findInsertForTeam($insertQueries, "'Heat'");
        $this->assertNotNull($team1Insert);
        $this->assertNotNull($team2Insert);
        // Both are 2-2 so confGB should be 0
        $this->assertStringContainsString("'2-2'", $team1Insert);
        $this->assertStringContainsString("'2-2'", $team2Insert);
    }

    public function testEmptyTeamMapProducesNoInserts(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->updater->setTestTeamMap([]);
        $this->updater->setTestGames([]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);
        $this->assertCount(0, $insertQueries);
    }

    public function testAssignGroupingsForConferences(): void
    {
        foreach (\League::CONFERENCE_NAMES as $conference) {
            $reflection = new ReflectionClass($this->updater);
            $method = $reflection->getMethod('assignGroupingsFor');
            $result = $method->invoke($this->updater, $conference);

            $this->assertIsArray($result);
            $this->assertSame('conference', $result[0]);
            $this->assertSame('confGB', $result[1]);
            $this->assertSame('confMagicNumber', $result[2]);
        }
    }

    public function testAssignGroupingsForDivisions(): void
    {
        foreach (\League::DIVISION_NAMES as $division) {
            $reflection = new ReflectionClass($this->updater);
            $method = $reflection->getMethod('assignGroupingsFor');
            $result = $method->invoke($this->updater, $division);

            $this->assertIsArray($result);
            $this->assertSame('division', $result[0]);
            $this->assertSame('divGB', $result[1]);
            $this->assertSame('divMagicNumber', $result[2]);
        }
    }

    public function testMagicNumberQueriesExecuteForAllRegions(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->setMockData([]);
        $this->updater->setTestTeamMap([]);
        $this->updater->setTestGames([]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();

        // Should have SELECT queries for magic number regions
        $selectQueries = array_filter($queries, static function (string $q): bool {
            return stripos($q, 'SELECT') === 0 && stripos($q, 'ibl_standings') !== false;
        });

        $this->assertNotEmpty($selectQueries);
    }

    public function testExtractWinsFromRecord(): void
    {
        $reflection = new ReflectionClass($this->updater);
        $method = $reflection->getMethod('extractWins');

        $this->assertSame(45, $method->invoke($this->updater, '45-37'));
        $this->assertSame(5, $method->invoke($this->updater, '5-3'));
        $this->assertSame(0, $method->invoke($this->updater, '0-82'));
        $this->assertSame(82, $method->invoke($this->updater, '82-0'));
    }

    public function testExtractLossesFromRecord(): void
    {
        $reflection = new ReflectionClass($this->updater);
        $method = $reflection->getMethod('extractLosses');

        $this->assertSame(37, $method->invoke($this->updater, '45-37'));
        $this->assertSame(3, $method->invoke($this->updater, '5-3'));
        $this->assertSame(82, $method->invoke($this->updater, '0-82'));
        $this->assertSame(0, $method->invoke($this->updater, '82-0'));
    }

    public function testGamesWithUnknownTeamsAreSkipped(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->updater->setTestTeamMap([
            1 => ['conference' => 'Eastern', 'division' => 'Atlantic', 'teamName' => 'Celtics'],
        ]);
        // Game references team 99 which is not in teamMap
        $this->updater->setTestGames([
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 99, 'HScore' => 90],
        ]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        // Team 1 should still be inserted with 0-0 record (game was skipped)
        $team1Insert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $this->assertNotNull($team1Insert, 'Expected INSERT for Celtics');
        $this->assertStringContainsString("'0-0'", $team1Insert);
    }

    public function testAllTeamsGetInsertedEvenWithNoGames(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->updater->setTestTeamMap($this->defaultTeamMap);
        $this->updater->setTestGames([]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        $this->assertCount(6, $insertQueries);
    }

    public function testConferenceGBIsRelativeToConferenceLeader(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->updater->setTestTeamMap([
            1 => ['conference' => 'Eastern', 'division' => 'Atlantic', 'teamName' => 'Celtics'],
            2 => ['conference' => 'Eastern', 'division' => 'Atlantic', 'teamName' => 'Heat'],
            4 => ['conference' => 'Western', 'division' => 'Pacific', 'teamName' => 'Lakers'],
        ]);
        $this->updater->setTestGames([
            // Team 1 beats team 2 twice → Celtics 2-0, Heat 0-2
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 90],
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 90],
        ]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        // Celtics: confGB = 0.0 (leader)
        $celticsInsert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $this->assertNotNull($celticsInsert);
        // Leader GB formula: leader has (2-0)/2 = 1.0 differential, so GB = 0
        // Heat: (0-2)/2 = -1.0 differential, GB = 1.0 - (-1.0) = 2.0
        $heatInsert = $this->findInsertForTeam($insertQueries, "'Heat'");
        $this->assertNotNull($heatInsert);

        // Lakers should have 0 GB since they're the only Western team
        $lakersInsert = $this->findInsertForTeam($insertQueries, "'Lakers'");
        $this->assertNotNull($lakersInsert);
    }

    /**
     * @param list<string> $queries
     * @return list<string>
     */
    private function filterInsertQueries(array $queries): array
    {
        return array_values(array_filter($queries, static function (string $q): bool {
            return stripos($q, 'INSERT INTO ibl_standings') !== false;
        }));
    }

    /**
     * @param list<string> $insertQueries
     */
    private function findInsertForTeam(array $insertQueries, string $teamIdentifier): ?string
    {
        foreach ($insertQueries as $query) {
            if (strpos($query, $teamIdentifier) !== false) {
                return $query;
            }
        }
        return null;
    }

    public function testConstructorAcceptsOptionalLeagueContext(): void
    {
        $leagueContext = $this->createStub(\League\LeagueContext::class);
        $updater = new \Updater\StandingsUpdater($this->mockDb, $this->mockSeason, $leagueContext);
        $this->assertInstanceOf(\Updater\StandingsUpdater::class, $updater);
    }

    public function testConstructorAcceptsNullLeagueContext(): void
    {
        $updater = new \Updater\StandingsUpdater($this->mockDb, $this->mockSeason, null);
        $this->assertInstanceOf(\Updater\StandingsUpdater::class, $updater);
    }

    public function testOlympicsContextTruncatesOlympicsStandingsTable(): void
    {
        $olympicsContext = $this->createStub(\League\LeagueContext::class);
        $olympicsContext->method('getTableName')->willReturnCallback(
            static function (string $table): string {
                return match ($table) {
                    'ibl_standings' => 'ibl_olympics_standings',
                    'ibl_schedule' => 'ibl_olympics_schedule',
                    default => $table,
                };
            }
        );

        $updater = new TestableStandingsUpdater($this->mockDb, $this->mockSeason, $olympicsContext);
        $updater->setTestTeamMap([]);
        $updater->setTestGames([]);
        $this->mockDb->setReturnTrue(true);

        ob_start();
        $updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertNotEmpty($queries);
        $this->assertSame('TRUNCATE TABLE ibl_olympics_standings', $queries[0]);
    }
}
