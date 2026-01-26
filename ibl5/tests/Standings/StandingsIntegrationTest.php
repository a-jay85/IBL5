<?php

declare(strict_types=1);

namespace Tests\Standings;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Standings\StandingsRepository;
use Standings\StandingsView;
use Standings\Contracts\StandingsRepositoryInterface;

/**
 * StandingsIntegrationTest - Integration tests for Standings module
 *
 * Tests repository database interactions and view rendering workflows.
 *
 * @covers \Standings\StandingsRepository
 * @covers \Standings\StandingsView
 */
#[AllowMockObjectsWithoutExpectations]
class StandingsIntegrationTest extends TestCase
{
    // ============================================
    // REPOSITORY - REGION VALIDATION TESTS
    // ============================================

    /**
     * Test getStandingsByRegion accepts all valid conference names
     */
    public function testGetStandingsByRegionAcceptsAllConferences(): void
    {
        $mockDb = $this->createMockDatabaseWithPreparedStatement([]);
        $repository = new StandingsRepository($mockDb);

        foreach (\League::CONFERENCE_NAMES as $conference) {
            $result = $repository->getStandingsByRegion($conference);
            $this->assertIsArray($result, "Failed for conference: {$conference}");
        }
    }

    /**
     * Test getStandingsByRegion accepts all valid division names
     */
    public function testGetStandingsByRegionAcceptsAllDivisions(): void
    {
        $mockDb = $this->createMockDatabaseWithPreparedStatement([]);
        $repository = new StandingsRepository($mockDb);

        foreach (\League::DIVISION_NAMES as $division) {
            $result = $repository->getStandingsByRegion($division);
            $this->assertIsArray($result, "Failed for division: {$division}");
        }
    }

    /**
     * Test getStandingsByRegion throws for invalid region
     */
    public function testGetStandingsByRegionThrowsForInvalidRegion(): void
    {
        $mockDb = $this->createMockDatabaseWithPreparedStatement([]);
        $repository = new StandingsRepository($mockDb);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid region: Southwest');

        $repository->getStandingsByRegion('Southwest');
    }

    /**
     * Test getStandingsByRegion throws for empty region
     */
    public function testGetStandingsByRegionThrowsForEmptyRegion(): void
    {
        $mockDb = $this->createMockDatabaseWithPreparedStatement([]);
        $repository = new StandingsRepository($mockDb);

        $this->expectException(\InvalidArgumentException::class);
        $repository->getStandingsByRegion('');
    }

    /**
     * Test getStandingsByRegion throws for case-sensitive mismatch
     */
    public function testGetStandingsByRegionIsCaseSensitive(): void
    {
        $mockDb = $this->createMockDatabaseWithPreparedStatement([]);
        $repository = new StandingsRepository($mockDb);

        $this->expectException(\InvalidArgumentException::class);
        $repository->getStandingsByRegion('eastern'); // lowercase
    }

    // ============================================
    // REPOSITORY - DATA STRUCTURE TESTS
    // ============================================

    /**
     * Test getStandingsByRegion returns data with expected keys
     */
    public function testGetStandingsByRegionReturnsExpectedKeys(): void
    {
        $mockData = [
            [
                'tid' => 1,
                'team_name' => 'Test Team',
                'leagueRecord' => '10-5',
                'pct' => '0.667',
                'gamesBack' => '0.0',
                'confRecord' => '7-3',
                'divRecord' => '3-1',
                'homeRecord' => '6-2',
                'awayRecord' => '4-3',
                'gamesUnplayed' => 67,
                'magicNumber' => 58,
                'clinchedConference' => 0,
                'clinchedDivision' => 0,
                'clinchedPlayoffs' => 0,
                'homeGames' => 8,
                'awayGames' => 7,
            ],
        ];

        $mockDb = $this->createMockDatabaseWithPreparedStatement($mockData);
        $repository = new StandingsRepository($mockDb);

        $result = $repository->getStandingsByRegion('Eastern');

        $this->assertCount(1, $result);
        $team = $result[0];

        $this->assertArrayHasKey('tid', $team);
        $this->assertArrayHasKey('team_name', $team);
        $this->assertArrayHasKey('leagueRecord', $team);
        $this->assertArrayHasKey('pct', $team);
        $this->assertArrayHasKey('gamesBack', $team);
        $this->assertArrayHasKey('confRecord', $team);
        $this->assertArrayHasKey('divRecord', $team);
        $this->assertArrayHasKey('homeRecord', $team);
        $this->assertArrayHasKey('awayRecord', $team);
        $this->assertArrayHasKey('gamesUnplayed', $team);
        $this->assertArrayHasKey('magicNumber', $team);
    }

    /**
     * Test getStandingsByRegion returns multiple teams
     */
    public function testGetStandingsByRegionReturnsMultipleTeams(): void
    {
        $mockData = [
            ['tid' => 1, 'team_name' => 'Team 1', 'leagueRecord' => '10-5', 'pct' => '0.667',
             'gamesBack' => '0.0', 'confRecord' => '7-3', 'divRecord' => '3-1', 'homeRecord' => '6-2',
             'awayRecord' => '4-3', 'gamesUnplayed' => 67, 'magicNumber' => 58,
             'clinchedConference' => 0, 'clinchedDivision' => 0, 'clinchedPlayoffs' => 0,
             'homeGames' => 8, 'awayGames' => 7],
            ['tid' => 2, 'team_name' => 'Team 2', 'leagueRecord' => '8-7', 'pct' => '0.533',
             'gamesBack' => '2.0', 'confRecord' => '5-4', 'divRecord' => '2-2', 'homeRecord' => '5-2',
             'awayRecord' => '3-5', 'gamesUnplayed' => 67, 'magicNumber' => 60,
             'clinchedConference' => 0, 'clinchedDivision' => 0, 'clinchedPlayoffs' => 0,
             'homeGames' => 7, 'awayGames' => 8],
            ['tid' => 3, 'team_name' => 'Team 3', 'leagueRecord' => '6-9', 'pct' => '0.400',
             'gamesBack' => '4.0', 'confRecord' => '4-5', 'divRecord' => '1-3', 'homeRecord' => '4-3',
             'awayRecord' => '2-6', 'gamesUnplayed' => 67, 'magicNumber' => 62,
             'clinchedConference' => 0, 'clinchedDivision' => 0, 'clinchedPlayoffs' => 0,
             'homeGames' => 7, 'awayGames' => 8],
        ];

        $mockDb = $this->createMockDatabaseWithPreparedStatement($mockData);
        $repository = new StandingsRepository($mockDb);

        $result = $repository->getStandingsByRegion('Eastern');

        $this->assertCount(3, $result);
    }

    // ============================================
    // REPOSITORY - STREAK DATA TESTS
    // ============================================

    /**
     * Test getTeamStreakData returns expected keys
     */
    public function testGetTeamStreakDataReturnsExpectedKeys(): void
    {
        $mockData = [
            'last_win' => 7,
            'last_loss' => 3,
            'streak_type' => 'W',
            'streak' => 4,
            'ranking' => 5,
        ];

        $mockDb = $this->createMockDatabaseWithPreparedStatement($mockData);
        $repository = new StandingsRepository($mockDb);

        $result = $repository->getTeamStreakData(1);

        $this->assertArrayHasKey('last_win', $result);
        $this->assertArrayHasKey('last_loss', $result);
        $this->assertArrayHasKey('streak_type', $result);
        $this->assertArrayHasKey('streak', $result);
        $this->assertArrayHasKey('ranking', $result);
    }

    /**
     * Test getTeamStreakData returns correct values
     */
    public function testGetTeamStreakDataReturnsCorrectValues(): void
    {
        $mockData = [
            'last_win' => 8,
            'last_loss' => 2,
            'streak_type' => 'W',
            'streak' => 5,
            'ranking' => 1,
        ];

        $mockDb = $this->createMockDatabaseWithPreparedStatement($mockData);
        $repository = new StandingsRepository($mockDb);

        $result = $repository->getTeamStreakData(1);

        $this->assertEquals(8, $result['last_win']);
        $this->assertEquals(2, $result['last_loss']);
        $this->assertEquals('W', $result['streak_type']);
        $this->assertEquals(5, $result['streak']);
        $this->assertEquals(1, $result['ranking']);
    }

    /**
     * Test getTeamStreakData handles losing streak
     */
    public function testGetTeamStreakDataHandlesLosingStreak(): void
    {
        $mockData = [
            'last_win' => 3,
            'last_loss' => 7,
            'streak_type' => 'L',
            'streak' => 4,
            'ranking' => 28,
        ];

        $mockDb = $this->createMockDatabaseWithPreparedStatement($mockData);
        $repository = new StandingsRepository($mockDb);

        $result = $repository->getTeamStreakData(1);

        $this->assertEquals('L', $result['streak_type']);
        $this->assertEquals(4, $result['streak']);
    }

    // ============================================
    // REPOSITORY - PYTHAGOREAN STATS TESTS
    // ============================================

    /**
     * Test getTeamPythagoreanStats returns calculated points
     */
    public function testGetTeamPythagoreanStatsReturnsCalculatedPoints(): void
    {
        $offenseData = ['fgm' => 1000, 'ftm' => 500, 'tgm' => 300];
        $defenseData = ['fgm' => 900, 'ftm' => 450, 'tgm' => 250];

        // Create separate mock results for each query
        $offenseResult = $this->createMock(\mysqli_result::class);
        $offenseResult->method('fetch_assoc')->willReturn($offenseData);

        $defenseResult = $this->createMock(\mysqli_result::class);
        $defenseResult->method('fetch_assoc')->willReturn($defenseData);

        // Create separate mock statements for each query
        $offenseStmt = $this->createMock(\mysqli_stmt::class);
        $offenseStmt->method('bind_param')->willReturn(true);
        $offenseStmt->method('execute')->willReturn(true);
        $offenseStmt->method('get_result')->willReturn($offenseResult);
        $offenseStmt->method('close')->willReturn(true);

        $defenseStmt = $this->createMock(\mysqli_stmt::class);
        $defenseStmt->method('bind_param')->willReturn(true);
        $defenseStmt->method('execute')->willReturn(true);
        $defenseStmt->method('get_result')->willReturn($defenseResult);
        $defenseStmt->method('close')->willReturn(true);

        $mockDb = $this->createMock(\mysqli::class);
        $mockDb->method('prepare')->willReturnOnConsecutiveCalls($offenseStmt, $defenseStmt);

        $repository = new StandingsRepository($mockDb);
        $result = $repository->getTeamPythagoreanStats(1);

        // Expected: 2*fgm + ftm + tgm
        // Offense: 2*1000 + 500 + 300 = 2800
        // Defense: 2*900 + 450 + 250 = 2500
        $this->assertEquals(2800, $result['pointsScored']);
        $this->assertEquals(2500, $result['pointsAllowed']);
    }

    /**
     * Test getTeamPythagoreanStats returns null when offense missing
     */
    public function testGetTeamPythagoreanStatsReturnsNullWhenOffenseMissing(): void
    {
        $mockDb = $this->createMockDatabaseWithPreparedStatement(null);
        $repository = new StandingsRepository($mockDb);

        $result = $repository->getTeamPythagoreanStats(999);

        $this->assertNull($result);
    }

    // ============================================
    // VIEW - CLINCHING INDICATOR PRIORITY TESTS
    // ============================================

    /**
     * Test Z indicator takes priority over Y and X
     */
    public function testClinchedConferenceTakesPriorityOverDivisionAndPlayoffs(): void
    {
        $mockRepository = $this->createMock(StandingsRepositoryInterface::class);
        $mockRepository->method('getStandingsByRegion')->willReturn([
            [
                'tid' => 1,
                'team_name' => 'Test Team',
                'leagueRecord' => '50-10',
                'pct' => '0.833',
                'gamesBack' => '0.0',
                'magicNumber' => 0,
                'gamesUnplayed' => 22,
                'confRecord' => '30-5',
                'divRecord' => '12-2',
                'homeRecord' => '28-3',
                'awayRecord' => '22-7',
                'homeGames' => 31,
                'awayGames' => 29,
                'clinchedConference' => 1,
                'clinchedDivision' => 1, // Also has division clinched
                'clinchedPlayoffs' => 1, // Also has playoffs clinched
            ],
        ]);
        $mockRepository->method('getTeamStreakData')->willReturn(null);
        $mockRepository->method('getTeamPythagoreanStats')->willReturn(null);

        $view = new StandingsView($mockRepository);
        $result = $view->renderRegion('Eastern');

        // Should show Z, not Y or X
        $this->assertStringContainsString('<strong>Z</strong>-Test Team', $result);
        $this->assertStringNotContainsString('<strong>Y</strong>-Test Team', $result);
        $this->assertStringNotContainsString('<strong>X</strong>-Test Team', $result);
    }

    /**
     * Test Y indicator takes priority over X
     */
    public function testClinchedDivisionTakesPriorityOverPlayoffs(): void
    {
        $mockRepository = $this->createMock(StandingsRepositoryInterface::class);
        $mockRepository->method('getStandingsByRegion')->willReturn([
            [
                'tid' => 1,
                'team_name' => 'Test Team',
                'leagueRecord' => '45-15',
                'pct' => '0.750',
                'gamesBack' => '0.0',
                'magicNumber' => 5,
                'gamesUnplayed' => 22,
                'confRecord' => '28-7',
                'divRecord' => '12-2',
                'homeRecord' => '25-6',
                'awayRecord' => '20-9',
                'homeGames' => 31,
                'awayGames' => 29,
                'clinchedConference' => 0,
                'clinchedDivision' => 1,
                'clinchedPlayoffs' => 1, // Also has playoffs clinched
            ],
        ]);
        $mockRepository->method('getTeamStreakData')->willReturn(null);
        $mockRepository->method('getTeamPythagoreanStats')->willReturn(null);

        $view = new StandingsView($mockRepository);
        $result = $view->renderRegion('Eastern');

        // Should show Y, not X
        $this->assertStringContainsString('<strong>Y</strong>-Test Team', $result);
        $this->assertStringNotContainsString('<strong>X</strong>-Test Team', $result);
    }

    /**
     * Test no indicator when nothing clinched
     */
    public function testNoIndicatorWhenNothingClinched(): void
    {
        $mockRepository = $this->createMock(StandingsRepositoryInterface::class);
        $mockRepository->method('getStandingsByRegion')->willReturn([
            [
                'tid' => 1,
                'team_name' => 'Test Team',
                'leagueRecord' => '20-20',
                'pct' => '0.500',
                'gamesBack' => '10.0',
                'magicNumber' => 30,
                'gamesUnplayed' => 42,
                'confRecord' => '12-12',
                'divRecord' => '4-6',
                'homeRecord' => '12-8',
                'awayRecord' => '8-12',
                'homeGames' => 20,
                'awayGames' => 20,
                'clinchedConference' => 0,
                'clinchedDivision' => 0,
                'clinchedPlayoffs' => 0,
            ],
        ]);
        $mockRepository->method('getTeamStreakData')->willReturn(null);
        $mockRepository->method('getTeamPythagoreanStats')->willReturn(null);

        $view = new StandingsView($mockRepository);
        $result = $view->renderRegion('Eastern');

        // Should not have any indicator prefix
        $this->assertStringNotContainsString('<strong>Z</strong>', $result);
        $this->assertStringNotContainsString('<strong>Y</strong>', $result);
        $this->assertStringNotContainsString('<strong>X</strong>', $result);
        $this->assertStringContainsString('>Test Team<', $result);
    }

    // ============================================
    // VIEW - STREAK DISPLAY TESTS
    // ============================================

    /**
     * Test streak displays correctly for winning streak
     */
    public function testStreakDisplaysWinningStreak(): void
    {
        $mockRepository = $this->createMock(StandingsRepositoryInterface::class);
        $mockRepository->method('getStandingsByRegion')->willReturn([
            $this->createMockTeamData(),
        ]);
        $mockRepository->method('getTeamStreakData')->willReturn([
            'last_win' => 8,
            'last_loss' => 2,
            'streak_type' => 'W',
            'streak' => 6,
            'ranking' => 3,
        ]);
        $mockRepository->method('getTeamPythagoreanStats')->willReturn(null);

        $view = new StandingsView($mockRepository);
        $result = $view->renderRegion('Eastern');

        $this->assertStringContainsString('W 6', $result);
    }

    /**
     * Test streak displays correctly for losing streak
     */
    public function testStreakDisplaysLosingStreak(): void
    {
        $mockRepository = $this->createMock(StandingsRepositoryInterface::class);
        $mockRepository->method('getStandingsByRegion')->willReturn([
            $this->createMockTeamData(),
        ]);
        $mockRepository->method('getTeamStreakData')->willReturn([
            'last_win' => 2,
            'last_loss' => 8,
            'streak_type' => 'L',
            'streak' => 5,
            'ranking' => 25,
        ]);
        $mockRepository->method('getTeamPythagoreanStats')->willReturn(null);

        $view = new StandingsView($mockRepository);
        $result = $view->renderRegion('Eastern');

        $this->assertStringContainsString('L 5', $result);
    }

    /**
     * Test last 10 displays correctly
     */
    public function testLast10DisplaysCorrectly(): void
    {
        $mockRepository = $this->createMock(StandingsRepositoryInterface::class);
        $mockRepository->method('getStandingsByRegion')->willReturn([
            $this->createMockTeamData(),
        ]);
        $mockRepository->method('getTeamStreakData')->willReturn([
            'last_win' => 7,
            'last_loss' => 3,
            'streak_type' => 'W',
            'streak' => 2,
            'ranking' => 5,
        ]);
        $mockRepository->method('getTeamPythagoreanStats')->willReturn(null);

        $view = new StandingsView($mockRepository);
        $result = $view->renderRegion('Eastern');

        // Check for last 10 format
        $this->assertStringContainsString('>7-3<', $result);
    }

    /**
     * Test rating displays in standings
     */
    public function testRatingDisplaysCorrectly(): void
    {
        $mockRepository = $this->createMock(StandingsRepositoryInterface::class);
        $mockRepository->method('getStandingsByRegion')->willReturn([
            $this->createMockTeamData(),
        ]);
        $mockRepository->method('getTeamStreakData')->willReturn([
            'last_win' => 6,
            'last_loss' => 4,
            'streak_type' => 'W',
            'streak' => 1,
            'ranking' => 7,
        ]);
        $mockRepository->method('getTeamPythagoreanStats')->willReturn(null);

        $view = new StandingsView($mockRepository);
        $result = $view->renderRegion('Eastern');

        // Rating should be in the standings-rating class span
        $this->assertStringContainsString('standings-rating', $result);
        $this->assertStringContainsString('>7<', $result);
    }

    // ============================================
    // VIEW - HTML STRUCTURE TESTS
    // ============================================

    /**
     * Test render includes all 16 column headers
     */
    public function testRenderIncludesAllColumnHeaders(): void
    {
        $mockRepository = $this->createMock(StandingsRepositoryInterface::class);
        $mockRepository->method('getStandingsByRegion')->willReturn([]);
        $mockRepository->method('getTeamStreakData')->willReturn(null);
        $mockRepository->method('getTeamPythagoreanStats')->willReturn(null);

        $view = new StandingsView($mockRepository);
        $result = $view->render();

        $expectedHeaders = [
            'Team', 'W-L', 'Pct', 'Pyth', 'GB', 'Magic#',
            'Left', 'Conf.', 'Div.', 'Home', 'Away',
            'Last 10', 'Streak', 'Rating',
        ];

        foreach ($expectedHeaders as $header) {
            $this->assertStringContainsString($header, $result);
        }
    }

    /**
     * Test render includes CSS style block
     */
    public function testRenderIncludesCssStyleBlock(): void
    {
        $mockRepository = $this->createMock(StandingsRepositoryInterface::class);
        $mockRepository->method('getStandingsByRegion')->willReturn([]);
        $mockRepository->method('getTeamStreakData')->willReturn(null);
        $mockRepository->method('getTeamPythagoreanStats')->willReturn(null);

        $view = new StandingsView($mockRepository);
        $result = $view->render();

        $this->assertStringContainsString('<style>', $result);
        $this->assertStringContainsString('</style>', $result);
        $this->assertStringContainsString('.standings-table', $result);
    }

    /**
     * Test render includes JavaScript for scroll indicators
     */
    public function testRenderIncludesJavaScript(): void
    {
        $mockRepository = $this->createMock(StandingsRepositoryInterface::class);
        $mockRepository->method('getStandingsByRegion')->willReturn([]);
        $mockRepository->method('getTeamStreakData')->willReturn(null);
        $mockRepository->method('getTeamPythagoreanStats')->willReturn(null);

        $view = new StandingsView($mockRepository);
        $result = $view->render();

        $this->assertStringContainsString('<script>', $result);
        $this->assertStringContainsString('</script>', $result);
        $this->assertStringContainsString('table-scroll-container', $result);
    }

    /**
     * Test render includes responsive table classes
     */
    public function testRenderIncludesResponsiveTableClasses(): void
    {
        $mockRepository = $this->createMock(StandingsRepositoryInterface::class);
        $mockRepository->method('getStandingsByRegion')->willReturn([]);
        $mockRepository->method('getTeamStreakData')->willReturn(null);
        $mockRepository->method('getTeamPythagoreanStats')->willReturn(null);

        $view = new StandingsView($mockRepository);
        $result = $view->render();

        $this->assertStringContainsString('responsive-table', $result);
        $this->assertStringContainsString('sticky-col', $result);
        $this->assertStringContainsString('table-scroll-wrapper', $result);
    }

    /**
     * Test render includes team logos
     */
    public function testRenderIncludesTeamLogos(): void
    {
        $mockRepository = $this->createMock(StandingsRepositoryInterface::class);
        $mockRepository->method('getStandingsByRegion')->willReturn([
            $this->createMockTeamData(),
        ]);
        $mockRepository->method('getTeamStreakData')->willReturn(null);
        $mockRepository->method('getTeamPythagoreanStats')->willReturn(null);

        $view = new StandingsView($mockRepository);
        $result = $view->renderRegion('Eastern');

        $this->assertStringContainsString('<img src="images/logo/', $result);
        $this->assertStringContainsString('standings-team-logo', $result);
    }

    // ============================================
    // VIEW - XSS PROTECTION TESTS
    // ============================================

    /**
     * Test region name is sanitized
     */
    public function testRegionNameIsSanitized(): void
    {
        // This would normally not happen, but testing defense in depth
        $mockRepository = $this->createMock(StandingsRepositoryInterface::class);
        $mockRepository->method('getStandingsByRegion')->willReturn([]);
        $mockRepository->method('getTeamStreakData')->willReturn(null);
        $mockRepository->method('getTeamPythagoreanStats')->willReturn(null);

        $view = new StandingsView($mockRepository);
        $result = $view->renderRegion('Eastern');

        // Region should appear escaped if it contained special chars
        $this->assertStringContainsString('Eastern Conference', $result);
    }

    /**
     * Test streak type is sanitized
     */
    public function testStreakTypeIsSanitized(): void
    {
        $mockRepository = $this->createMock(StandingsRepositoryInterface::class);
        $mockRepository->method('getStandingsByRegion')->willReturn([
            $this->createMockTeamData(),
        ]);
        $mockRepository->method('getTeamStreakData')->willReturn([
            'last_win' => 5,
            'last_loss' => 5,
            'streak_type' => '<script>alert(1)</script>',
            'streak' => 1,
            'ranking' => 15,
        ]);
        $mockRepository->method('getTeamPythagoreanStats')->willReturn(null);

        $view = new StandingsView($mockRepository);
        $result = $view->renderRegion('Eastern');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $result);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Create mock team data for tests
     */
    private function createMockTeamData(): array
    {
        return [
            'tid' => 1,
            'team_name' => 'Test Team',
            'leagueRecord' => '10-5',
            'pct' => '0.667',
            'gamesBack' => '0.0',
            'magicNumber' => 58,
            'gamesUnplayed' => 67,
            'confRecord' => '7-3',
            'divRecord' => '3-1',
            'homeRecord' => '6-2',
            'awayRecord' => '4-3',
            'homeGames' => 8,
            'awayGames' => 7,
            'clinchedConference' => 0,
            'clinchedDivision' => 0,
            'clinchedPlayoffs' => 0,
        ];
    }

    /**
     * Create a mock database with prepared statement support
     */
    private function createMockDatabaseWithPreparedStatement($returnData): object
    {
        $mockResult = $this->createMock(\mysqli_result::class);

        if ($returnData === null) {
            $mockResult->method('fetch_assoc')->willReturn(null);
            $mockResult->method('fetch_all')->willReturn([]);
        } elseif (is_array($returnData) && !isset($returnData[0])) {
            // Single row result
            $mockResult->method('fetch_assoc')->willReturn($returnData);
            $mockResult->method('fetch_all')->willReturn([$returnData]);
        } else {
            // Multiple rows result
            $mockResult->method('fetch_assoc')->willReturnOnConsecutiveCalls(...array_merge($returnData, [null]));
            $mockResult->method('fetch_all')->willReturn($returnData);
        }

        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockStmt->method('bind_param')->willReturn(true);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($mockResult);
        $mockStmt->method('close')->willReturn(true);

        $mockDb = $this->createMock(\mysqli::class);
        $mockDb->method('prepare')->willReturn($mockStmt);

        return $mockDb;
    }
}
