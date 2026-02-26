<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Updater\PowerRankingsUpdater;
use Statistics\TeamStatsCalculator;

/**
 * Testable subclass that exposes protected methods for testing
 */
class TestablePowerRankingsUpdater extends PowerRankingsUpdater
{
    public function publicDetermineMonth(): int
    {
        return $this->determineMonth();
    }

    public function publicCalculateTeamStats(array $games, int $tid): array
    {
        return $this->calculateTeamStats($games, $tid);
    }
}

/**
 * Comprehensive tests for PowerRankingsUpdater class
 * 
 * Tests power rankings update functionality including:
 * - Month determination for different phases
 * - Team statistics calculation
 * - Win/loss tracking
 * - Streak tracking
 * - Games back calculation
 * - Ranking score calculation
 * - Season and HEAT records updates
 */
class PowerRankingsUpdaterTest extends TestCase
{
    private MockDatabase $mockDb;
    private \Season $mockSeason;
    private TestablePowerRankingsUpdater $powerRankingsUpdater;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->mockSeason = new \Season($this->mockDb);
        $this->powerRankingsUpdater = new TestablePowerRankingsUpdater($this->mockDb, $this->mockSeason);
    }

    protected function tearDown(): void
    {
        unset($this->powerRankingsUpdater);
        unset($this->mockDb);
        unset($this->mockSeason);
    }

    public function testDetermineMonthForPreseason(): void
    {
        $this->mockSeason->phase = 'Preseason';
        
        $result = $this->powerRankingsUpdater->publicDetermineMonth();
        
        $this->assertEquals(\Season::IBL_REGULAR_SEASON_STARTING_MONTH, $result);
    }

    public function testDetermineMonthForHEAT(): void
    {
        $this->mockSeason->phase = 'HEAT';
        
        $result = $this->powerRankingsUpdater->publicDetermineMonth();
        
        $this->assertEquals(\Season::IBL_HEAT_MONTH, $result);
    }

    public function testDetermineMonthForRegularSeason(): void
    {
        $this->mockSeason->phase = 'Regular Season';
        
        $result = $this->powerRankingsUpdater->publicDetermineMonth();
        
        $this->assertEquals(\Season::IBL_REGULAR_SEASON_STARTING_MONTH, $result);
    }

    public function testCalculateTeamStatsInitializesCorrectly(): void
    {
        $games = [];
        
        $result = $this->powerRankingsUpdater->publicCalculateTeamStats($games, 1);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('wins', $result);
        $this->assertArrayHasKey('losses', $result);
        $this->assertArrayHasKey('homeWins', $result);
        $this->assertArrayHasKey('homeLosses', $result);
        $this->assertArrayHasKey('awayWins', $result);
        $this->assertArrayHasKey('awayLosses', $result);
        $this->assertArrayHasKey('streak', $result);
        $this->assertArrayHasKey('streakType', $result);
        
        $this->assertEquals(0, $result['wins']);
        $this->assertEquals(0, $result['losses']);
    }

    public function testCalculateTeamStatsWithWinningGame(): void
    {
        $mockGames = [
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 95]
        ];
        
        $this->mockDb->setMockData([['win' => 5, 'loss' => 3]]);
        
        $result = $this->powerRankingsUpdater->publicCalculateTeamStats($mockGames, 1);
        
        $this->assertEquals(1, $result['wins']);
        $this->assertEquals(0, $result['losses']);
        $this->assertEquals(0, $result['homeWins']);
        $this->assertEquals(1, $result['awayWins']);
        $this->assertEquals('W', $result['streakType']);
        $this->assertEquals(1, $result['streak']);
    }

    public function testCalculateTeamStatsWithLosingGame(): void
    {
        $mockGames = [
            ['Visitor' => 1, 'VScore' => 85, 'Home' => 2, 'HScore' => 95]
        ];
        
        $this->mockDb->setMockData([['win' => 5, 'loss' => 3]]);
        
        $result = $this->powerRankingsUpdater->publicCalculateTeamStats($mockGames, 1);
        
        $this->assertEquals(0, $result['wins']);
        $this->assertEquals(1, $result['losses']);
        $this->assertEquals(0, $result['homeLosses']);
        $this->assertEquals(1, $result['awayLosses']);
        $this->assertEquals('L', $result['streakType']);
        $this->assertEquals(1, $result['streak']);
    }

    public function testCalculateTeamStatsWithHomeGame(): void
    {
        $mockGames = [
            ['Visitor' => 2, 'VScore' => 90, 'Home' => 1, 'HScore' => 100]
        ];
        
        $this->mockDb->setMockData([['win' => 5, 'loss' => 3]]);
        
        $result = $this->powerRankingsUpdater->publicCalculateTeamStats($mockGames, 1);
        
        $this->assertEquals(1, $result['wins']);
        $this->assertEquals(1, $result['homeWins']);
        $this->assertEquals(0, $result['awayWins']);
    }

    public function testCalculateTeamStatsTracksWinningStreak(): void
    {
        $mockGames = [
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 95],
            ['Visitor' => 3, 'VScore' => 85, 'Home' => 1, 'HScore' => 90],
            ['Visitor' => 1, 'VScore' => 105, 'Home' => 4, 'HScore' => 100],
        ];
        
        $this->mockDb->setMockData([['win' => 5, 'loss' => 3]]);
        
        $result = $this->powerRankingsUpdater->publicCalculateTeamStats($mockGames, 1);
        
        $this->assertEquals(3, $result['wins']);
        $this->assertEquals('W', $result['streakType']);
        $this->assertEquals(3, $result['streak']);
    }

    public function testCalculateTeamStatsTracksLosingStreak(): void
    {
        $mockGames = [
            ['Visitor' => 1, 'VScore' => 80, 'Home' => 2, 'HScore' => 95],
            ['Visitor' => 3, 'VScore' => 100, 'Home' => 1, 'HScore' => 90],
        ];
        
        $this->mockDb->setMockData([['win' => 5, 'loss' => 3]]);
        
        $result = $this->powerRankingsUpdater->publicCalculateTeamStats($mockGames, 1);
        
        $this->assertEquals(0, $result['wins']);
        $this->assertEquals(2, $result['losses']);
        $this->assertEquals('L', $result['streakType']);
        $this->assertEquals(2, $result['streak']);
    }

    public function testCalculateTeamStatsHandlesStreakChange(): void
    {
        $mockGames = [
            ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 95],
            ['Visitor' => 3, 'VScore' => 100, 'Home' => 1, 'HScore' => 90],
        ];
        
        $this->mockDb->setMockData([['win' => 5, 'loss' => 3]]);
        
        $result = $this->powerRankingsUpdater->publicCalculateTeamStats($mockGames, 1);
        
        $this->assertEquals(1, $result['wins']);
        $this->assertEquals(1, $result['losses']);
        $this->assertEquals('L', $result['streakType']);
        $this->assertEquals(1, $result['streak']);
    }

    public function testCalculateTeamStatsIgnoresTies(): void
    {
        $mockGames = [
            ['Visitor' => 1, 'VScore' => 95, 'Home' => 2, 'HScore' => 95]
        ];
        
        $result = $this->powerRankingsUpdater->publicCalculateTeamStats($mockGames, 1);
        
        $this->assertEquals(0, $result['wins']);
        $this->assertEquals(0, $result['losses']);
    }

    public function testUpdateResetsDepthChartStatus(): void
    {
        $mockTeams = [];
        $this->mockDb->setMockData($mockTeams);
        $this->mockDb->setReturnTrue(true);
        
        ob_start();
        $this->powerRankingsUpdater->update();
        ob_end_clean();
        
        $queries = $this->mockDb->getExecutedQueries();
        $depthChartResetQuery = array_filter($queries, function($q) {
            return stripos($q, 'sim_depth') !== false;
        });
        
        $this->assertNotEmpty($depthChartResetQuery);
    }

    public function testCalculateTeamStatsTracksLast10Games(): void
    {
        $mockGames = [];
        for ($i = 0; $i < 15; $i++) {
            if ($i < 8) {
                $mockGames[] = ['Visitor' => 1, 'VScore' => 80, 'Home' => 2, 'HScore' => 90];
            } else {
                $mockGames[] = ['Visitor' => 1, 'VScore' => 100, 'Home' => 2, 'HScore' => 90];
            }
        }
        
        $this->mockDb->setMockData([['win' => 5, 'loss' => 3]]);
        
        $result = $this->powerRankingsUpdater->publicCalculateTeamStats($mockGames, 1);
        
        $this->assertEquals(7, $result['wins']);
        $this->assertEquals(8, $result['losses']);
        $this->assertEquals(7, $result['winsInLast10Games']);
        $this->assertEquals(3, $result['lossesInLast10Games']);
    }

    public function testConstructorAcceptsOptionalLeagueContext(): void
    {
        $leagueContext = $this->createStub(\League\LeagueContext::class);
        $updater = new PowerRankingsUpdater($this->mockDb, $this->mockSeason, null, $leagueContext);
        $this->assertInstanceOf(PowerRankingsUpdater::class, $updater);
    }

    public function testConstructorAcceptsNullLeagueContext(): void
    {
        $updater = new PowerRankingsUpdater($this->mockDb, $this->mockSeason, null, null);
        $this->assertInstanceOf(PowerRankingsUpdater::class, $updater);
    }
}
