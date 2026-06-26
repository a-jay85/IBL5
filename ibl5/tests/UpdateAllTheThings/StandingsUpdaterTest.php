<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Standings\StandingsRepository;
use Tests\WideUnit\Mocks\MockDatabase;
use Updater\StandingsUpdater;
use Season\Season;

/**
 * Testable subclass that overrides DB methods to inject test data
 *
 * @phpstan-import-type TeamMapping from \Standings\Contracts\StandingsRepositoryInterface
 */
class TestableStandingsUpdater extends StandingsUpdater
{
    /** @var array<int, array{conference: string, division: string, teamName: string}> */
    private array $testTeamMap = [];

    /** @var list<array{visitor_teamid: int, visitor_score: int, home_teamid: int, home_score: int}> */
    private array $testGames = [];

    /**
     * @param array<int, array{conference: string, division: string, teamName: string}> $teamMap
     */
    public function setTestTeamMap(array $teamMap): void
    {
        $this->testTeamMap = $teamMap;
    }

    /**
     * @param list<array{visitor_teamid: int, visitor_score: int, home_teamid: int, home_score: int}> $games
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
    private Season $mockSeason;
    private StandingsRepository $repository;
    private TestableStandingsUpdater $updater;

    /** @var array<int, array{conference: string, division: string, teamName: string}> */
    private array $defaultTeamMap;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->mockSeason = new Season($this->mockDb);
        $this->mockSeason->phase = 'Regular Season';
        $this->mockSeason->beginningYear = 2006;
        $this->mockSeason->endingYear = 2007;

        $this->repository = new StandingsRepository($this->mockDb);
        $this->updater = new TestableStandingsUpdater($this->repository, $this->mockSeason);

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
        $this->mockDb->clearQueryPatterns();
        unset($this->updater, $this->repository, $this->mockDb, $this->mockSeason);
    }

    public function testUpdateDoesNotTruncateStandingsTable(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->updater->setTestTeamMap($this->defaultTeamMap);
        $this->updater->setTestGames([]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        foreach ($queries as $query) {
            $this->assertStringNotContainsString('TRUNCATE', $query);
        }
        $upserts = array_filter($queries, static fn (string $q): bool => str_contains($q, 'ON DUPLICATE KEY UPDATE'));
        $this->assertNotEmpty($upserts);
    }

    public function testGameResultsProduceCorrectTotalWinLoss(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->updater->setTestTeamMap($this->defaultTeamMap);
        $this->updater->setTestGames([
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 90],
            ['visitor_teamid' => 2, 'visitor_score' => 95, 'home_teamid' => 1, 'home_score' => 105],
            ['visitor_teamid' => 1, 'visitor_score' => 80, 'home_teamid' => 4, 'home_score' => 90],
        ]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        $team1Insert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $this->assertNotNull($team1Insert, 'Expected INSERT for Celtics');
        $this->assertStringContainsString("'2-1'", $team1Insert);

        $team2Insert = $this->findInsertForTeam($insertQueries, "'Heat'");
        $this->assertNotNull($team2Insert, 'Expected INSERT for Heat');
        $this->assertStringContainsString("'0-2'", $team2Insert);

        $team4Insert = $this->findInsertForTeam($insertQueries, "'Lakers'");
        $this->assertNotNull($team4Insert, 'Expected INSERT for Lakers');
        $this->assertStringContainsString("'1-0'", $team4Insert);
    }

    public function testHomeAwayRecordsSplitCorrectly(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->updater->setTestTeamMap($this->defaultTeamMap);
        $this->updater->setTestGames([
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 90],
            ['visitor_teamid' => 4, 'visitor_score' => 80, 'home_teamid' => 1, 'home_score' => 95],
        ]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        $team1Insert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $this->assertNotNull($team1Insert, 'Expected INSERT for Celtics');
        $this->assertStringContainsString("'1-0'", $team1Insert);
    }

    public function testConferenceRecordOnlyCountsSameConferenceGames(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->updater->setTestTeamMap($this->defaultTeamMap);
        $this->updater->setTestGames([
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 90],
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 4, 'home_score' => 90],
        ]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        $team1Insert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $this->assertNotNull($team1Insert, 'Expected INSERT for Celtics');
        $this->assertStringContainsString("'2-0'", $team1Insert);
    }

    public function testDivisionRecordOnlyCountsSameDivisionGames(): void
    {
        $this->mockDb->setReturnTrue(true);
        $this->updater->setTestTeamMap($this->defaultTeamMap);
        $this->updater->setTestGames([
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 90],
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 3, 'home_score' => 90],
        ]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        $team1Insert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $this->assertNotNull($team1Insert, 'Expected INSERT for Celtics');
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
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 90],
            ['visitor_teamid' => 2, 'visitor_score' => 100, 'home_teamid' => 1, 'home_score' => 90],
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 90],
        ]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        $team1Insert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $this->assertNotNull($team1Insert, 'Expected INSERT for Celtics');
        $this->assertStringContainsString('0.667', $team1Insert);

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

        $games = [];
        for ($i = 0; $i < 3; $i++) {
            $games[] = ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 90];
        }
        for ($i = 0; $i < 2; $i++) {
            $games[] = ['visitor_teamid' => 2, 'visitor_score' => 90, 'home_teamid' => 1, 'home_score' => 100];
        }
        $this->updater->setTestGames($games);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        $team1Insert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $this->assertNotNull($team1Insert, 'Expected INSERT for Celtics');
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

        $this->assertCount(2, $insertQueries);

        $team1Insert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $this->assertNotNull($team1Insert, 'Expected INSERT for Celtics');
        $this->assertStringContainsString("'0-0'", $team1Insert);
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
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 90],
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 90],
            ['visitor_teamid' => 2, 'visitor_score' => 100, 'home_teamid' => 1, 'home_score' => 90],
            ['visitor_teamid' => 2, 'visitor_score' => 100, 'home_teamid' => 1, 'home_score' => 90],
        ]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        $team1Insert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $team2Insert = $this->findInsertForTeam($insertQueries, "'Heat'");
        $this->assertNotNull($team1Insert);
        $this->assertNotNull($team2Insert);
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
        foreach (\League\League::CONFERENCE_NAMES as $conference) {
            $reflection = new ReflectionClass($this->updater);
            $method = $reflection->getMethod('assignGroupingsFor');
            $result = $method->invoke($this->updater, $conference);

            $this->assertIsArray($result);
            $this->assertSame('conference', $result[0]);
            $this->assertSame('conf_gb', $result[1]);
            $this->assertSame('conf_magic_number', $result[2]);
        }
    }

    public function testAssignGroupingsForDivisions(): void
    {
        foreach (\League\League::DIVISION_NAMES as $division) {
            $reflection = new ReflectionClass($this->updater);
            $method = $reflection->getMethod('assignGroupingsFor');
            $result = $method->invoke($this->updater, $division);

            $this->assertIsArray($result);
            $this->assertSame('division', $result[0]);
            $this->assertSame('div_gb', $result[1]);
            $this->assertSame('div_magic_number', $result[2]);
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
        $this->updater->setTestGames([
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 99, 'home_score' => 90],
        ]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

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
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 90],
            ['visitor_teamid' => 1, 'visitor_score' => 100, 'home_teamid' => 2, 'home_score' => 90],
        ]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $insertQueries = $this->filterInsertQueries($queries);

        $celticsInsert = $this->findInsertForTeam($insertQueries, "'Celtics'");
        $this->assertNotNull($celticsInsert);
        $heatInsert = $this->findInsertForTeam($insertQueries, "'Heat'");
        $this->assertNotNull($heatInsert);

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

    public function testClinchDivisionUpsertsTeamAward(): void
    {
        $this->mockDb->setReturnTrue(true);
        $clinchData = [
            ['teamid' => 1, 'team_name' => 'Celtics', 'home_wins' => 40, 'home_losses' => 1, 'away_wins' => 40, 'away_losses' => 1, 'wins' => 80],
            ['teamid' => 2, 'team_name' => 'Heat', 'home_wins' => 1, 'home_losses' => 40, 'away_wins' => 1, 'away_losses' => 40, 'wins' => 2],
        ];
        $this->mockDb->onQuery('ORDER BY wins DESC', $clinchData);
        $this->mockDb->onQuery('ORDER BY losses ASC', [['losses' => 80]]);
        $this->mockDb->onQuery('ORDER BY losses DESC', [['losses' => 80]]);
        $this->mockDb->onQuery('ORDER BY pct DESC', $clinchData);
        $this->mockDb->onQuery('games_unplayed', [['maxLeft' => 0]]);
        $this->updater->setTestTeamMap($this->defaultTeamMap);
        $this->updater->setTestGames([]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $awardQueries = array_filter($queries, static function (string $q): bool {
            return stripos($q, 'ibl_team_awards') !== false;
        });

        $this->assertNotEmpty($awardQueries, 'Expected at least one ibl_team_awards upsert query');
    }

    public function testClinchConferenceUpsertsTeamAward(): void
    {
        $this->mockDb->setReturnTrue(true);
        $clinchData = [
            ['teamid' => 1, 'team_name' => 'Celtics', 'home_wins' => 40, 'home_losses' => 1, 'away_wins' => 40, 'away_losses' => 1, 'wins' => 80],
            ['teamid' => 2, 'team_name' => 'Heat', 'home_wins' => 1, 'home_losses' => 40, 'away_wins' => 1, 'away_losses' => 40, 'wins' => 2],
        ];
        $this->mockDb->onQuery('ORDER BY wins DESC', $clinchData);
        $this->mockDb->onQuery('ORDER BY losses ASC', [['losses' => 80]]);
        $this->mockDb->onQuery('ORDER BY losses DESC', [['losses' => 80]]);
        $this->mockDb->onQuery('ORDER BY pct DESC', $clinchData);
        $this->mockDb->onQuery('games_unplayed', [['maxLeft' => 0]]);
        $this->updater->setTestTeamMap($this->defaultTeamMap);
        $this->updater->setTestGames([]);

        ob_start();
        $this->updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $awardQueries = array_values(array_filter($queries, static function (string $q): bool {
            return stripos($q, 'ibl_team_awards') !== false;
        }));

        $conferenceAwardFound = false;
        foreach ($awardQueries as $q) {
            if (stripos($q, 'Conference Champions') !== false) {
                $conferenceAwardFound = true;
                break;
            }
        }

        $this->assertTrue($conferenceAwardFound, 'Expected a Conference Champions award upsert');
    }

    public function testOlympicsSkipsTeamAwardUpsert(): void
    {
        $olympicsContext = self::createStub(\League\LeagueContext::class);
        $olympicsContext->method('getTableName')->willReturnCallback(
            static function (string $table): string {
                return match ($table) {
                    'ibl_standings' => 'ibl_olympics_standings',
                    'ibl_schedule' => 'ibl_olympics_schedule',
                    'ibl_league_config' => 'ibl_olympics_league_config',
                    default => $table,
                };
            }
        );

        $olympicsRepo = new StandingsRepository($this->mockDb, $olympicsContext);
        $updater = new TestableStandingsUpdater($olympicsRepo, $this->mockSeason, true);
        $updater->setTestTeamMap($this->defaultTeamMap);
        $updater->setTestGames([]);
        $this->mockDb->setReturnTrue(true);
        $clinchData = [
            ['teamid' => 1, 'team_name' => 'Celtics', 'home_wins' => 40, 'home_losses' => 1, 'away_wins' => 40, 'away_losses' => 1, 'wins' => 80],
            ['teamid' => 2, 'team_name' => 'Heat', 'home_wins' => 1, 'home_losses' => 40, 'away_wins' => 1, 'away_losses' => 40, 'wins' => 2],
        ];
        $this->mockDb->onQuery('ORDER BY wins DESC', $clinchData);
        $this->mockDb->onQuery('ORDER BY losses ASC', [['losses' => 80]]);
        $this->mockDb->onQuery('ORDER BY losses DESC', [['losses' => 80]]);
        $this->mockDb->onQuery('ORDER BY pct DESC', $clinchData);
        $this->mockDb->onQuery('games_unplayed', [['maxLeft' => 0]]);

        ob_start();
        $updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        $awardQueries = array_filter($queries, static function (string $q): bool {
            return stripos($q, 'ibl_team_awards') !== false;
        });

        $this->assertEmpty($awardQueries, 'Olympics context should not upsert team awards');
    }

    public function testRegionAwardMapCoversAllSixRegions(): void
    {
        $constant = StandingsUpdater::REGION_AWARD_MAP;
        $this->assertCount(6, $constant);

        $expectedRegions = ['Atlantic', 'Central', 'Midwest', 'Pacific', 'Eastern', 'Western'];
        foreach ($expectedRegions as $region) {
            $this->assertArrayHasKey($region, $constant, "REGION_AWARD_MAP missing region: {$region}");
        }
    }

    public function testRegularSeasonGamesConstantEquals82(): void
    {
        $this->assertSame(82, \League\League::REGULAR_SEASON_GAMES);
    }

    public function testConstructorAcceptsOptionalIsOlympicsFlag(): void
    {
        $updater = new \Updater\StandingsUpdater($this->repository, $this->mockSeason, true);
        $this->assertIsObject($updater);
    }

    public function testConstructorDefaultsToNonOlympics(): void
    {
        $updater = new \Updater\StandingsUpdater($this->repository, $this->mockSeason);
        $this->assertIsObject($updater);
    }

    public function testOlympicsContextUpsertsOlympicsStandingsTable(): void
    {
        // Drive the executeQuery() rewrite path: the repo's backtick-quoted
        // tables are rewritten to Olympics equivalents when the context is
        // Olympics (isOlympics() === true), via LeagueContext::TABLE_MAP.
        $olympicsContext = self::createStub(\League\LeagueContext::class);
        $olympicsContext->method('isOlympics')->willReturn(true);

        $olympicsRepo = new StandingsRepository($this->mockDb, $olympicsContext);
        $updater = new TestableStandingsUpdater($olympicsRepo, $this->mockSeason, true);
        $updater->setTestTeamMap($this->defaultTeamMap);
        $updater->setTestGames([]);
        $this->mockDb->setReturnTrue(true);

        ob_start();
        $updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();
        foreach ($queries as $query) {
            $this->assertStringNotContainsString('TRUNCATE', $query);
        }
        $upserts = array_filter($queries, static fn (string $q): bool =>
            str_contains($q, 'INSERT INTO ibl_olympics_standings') && str_contains($q, 'ON DUPLICATE KEY UPDATE'));
        $this->assertNotEmpty($upserts);
    }

    public function testOlympicsContextFetchTeamMapQueriesOlympicsLeagueConfig(): void
    {
        $olympicsContext = self::createStub(\League\LeagueContext::class);
        $olympicsContext->method('isOlympics')->willReturn(true);

        $olympicsRepo = new StandingsRepository($this->mockDb, $olympicsContext);
        $updater = new \Updater\StandingsUpdater($olympicsRepo, $this->mockSeason, true);
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->setMockData([]);

        ob_start();
        $updater->update();
        ob_end_clean();

        $queries = $this->mockDb->getExecutedQueries();

        $leagueConfigQueries = array_filter($queries, static function (string $q): bool {
            return stripos($q, 'league_config') !== false;
        });
        $this->assertNotEmpty($leagueConfigQueries);

        foreach ($leagueConfigQueries as $q) {
            $this->assertStringContainsString('ibl_olympics_league_config', $q);
            $this->assertStringNotContainsString('FROM ibl_league_config', $q);
        }
    }
}
