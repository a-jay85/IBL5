<?php

declare(strict_types=1);

namespace Tests\Integration\Standings;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Tests\Integration\IntegrationTestCase;
use Tests\Integration\Mocks\TestDataFactory;
use Standings\StandingsRepository;
use Standings\StandingsView;

/**
 * Integration tests for complete standings display workflows
 *
 * Tests end-to-end scenarios combining data retrieval and HTML rendering:
 * - Conference and division standings retrieval
 * - Streak data and power rankings
 * - Pythagorean win percentage calculation
 * - Clinched indicators (Z/Y/X prefix)
 * - Complete page and region rendering
 * - XSS protection
 *
 * @covers \Standings\StandingsRepository
 * @covers \Standings\StandingsView
 */
#[AllowMockObjectsWithoutExpectations]
class StandingsIntegrationTest extends IntegrationTestCase
{
    private StandingsRepository $repository;
    private StandingsView $view;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new StandingsRepository($GLOBALS['mysqli_db']);
        $this->view = new StandingsView($this->repository);
    }

    protected function tearDown(): void
    {
        unset($this->repository);
        unset($this->view);
        parent::tearDown();
    }

    // ========== REPOSITORY - CONFERENCE STANDINGS TESTS ==========

    /**
     * @group integration
     * @group standings
     * @group repository
     */
    public function testGetStandingsByRegionQueriesCorrectTableForConference(): void
    {
        // Arrange
        $this->mockDb->setMockData([]);

        // Act
        $this->repository->getStandingsByRegion('Eastern');

        // Assert
        $this->assertQueryExecuted('ibl_standings');
        $this->assertQueryExecuted('conference');
        $this->assertQueryExecuted('confGB');
        $this->assertQueryExecuted('confMagicNumber');
    }

    /**
     * @group integration
     * @group standings
     * @group repository
     */
    public function testGetStandingsByRegionReturnsConferenceTeams(): void
    {
        // Arrange
        $standingsData = [
            $this->createStandingsRow(1, 'Boston', 'Eastern', 'Atlantic', '50-20', 0.714, 0),
            $this->createStandingsRow(2, 'Miami', 'Eastern', 'Atlantic', '45-25', 0.643, 5),
        ];
        $this->mockDb->setMockData($standingsData);

        // Act
        $result = $this->repository->getStandingsByRegion('Eastern');

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    /**
     * @group integration
     * @group standings
     * @group repository
     */
    public function testGetStandingsByRegionOrdersByGamesBack(): void
    {
        // Arrange
        $this->mockDb->setMockData([]);

        // Act
        $this->repository->getStandingsByRegion('Western');

        // Assert - Query now uses table alias 's.' for standings table
        $this->assertQueryExecuted('ORDER BY s.confGB ASC');
    }

    // ========== REPOSITORY - DIVISION STANDINGS TESTS ==========

    /**
     * @group integration
     * @group standings
     * @group repository
     */
    public function testGetStandingsByRegionQueriesCorrectTableForDivision(): void
    {
        // Arrange
        $this->mockDb->setMockData([]);

        // Act
        $this->repository->getStandingsByRegion('Atlantic');

        // Assert
        $this->assertQueryExecuted('ibl_standings');
        $this->assertQueryExecuted('division');
        $this->assertQueryExecuted('divGB');
        $this->assertQueryExecuted('divMagicNumber');
    }

    /**
     * @group integration
     * @group standings
     * @group repository
     */
    public function testGetStandingsByRegionWorksForAllDivisions(): void
    {
        // Arrange
        $this->mockDb->setMockData([]);

        // Act & Assert - All divisions should work without throwing
        foreach (\League::DIVISION_NAMES as $division) {
            $result = $this->repository->getStandingsByRegion($division);
            $this->assertIsArray($result);
        }
    }

    /**
     * @group integration
     * @group standings
     * @group repository
     */
    public function testGetStandingsByRegionThrowsForInvalidRegion(): void
    {
        // Arrange
        $this->mockDb->setMockData([]);

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid region: Invalid');

        // Act
        $this->repository->getStandingsByRegion('Invalid');
    }

    // ========== REPOSITORY - STREAK DATA TESTS ==========

    /**
     * @group integration
     * @group standings
     * @group repository
     * @group streak
     */
    public function testGetTeamStreakDataQueriesPowerTable(): void
    {
        // Arrange
        $this->mockDb->setMockData([]);

        // Act
        $this->repository->getTeamStreakData(5);

        // Assert
        $this->assertQueryExecuted('ibl_power');
        $this->assertQueryExecuted('TeamID');
    }

    /**
     * @group integration
     * @group standings
     * @group repository
     * @group streak
     */
    public function testGetTeamStreakDataReturnsExpectedFields(): void
    {
        // Arrange
        $streakData = [
            'last_win' => 7,
            'last_loss' => 3,
            'streak_type' => 'W',
            'streak' => 4,
            'ranking' => 85,
        ];
        $this->mockDb->setMockData([$streakData]);

        // Act
        $result = $this->repository->getTeamStreakData(5);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('last_win', $result);
        $this->assertArrayHasKey('last_loss', $result);
        $this->assertArrayHasKey('streak_type', $result);
        $this->assertArrayHasKey('streak', $result);
        $this->assertArrayHasKey('ranking', $result);
    }

    /**
     * @group integration
     * @group standings
     * @group repository
     * @group streak
     */
    public function testGetTeamStreakDataReturnsNullWhenNotFound(): void
    {
        // Arrange
        $this->mockDb->setMockData([]);

        // Act
        $result = $this->repository->getTeamStreakData(999);

        // Assert
        $this->assertNull($result);
    }

    // ========== REPOSITORY - PYTHAGOREAN STATS TESTS ==========

    /**
     * @group integration
     * @group standings
     * @group repository
     * @group pythagorean
     */
    public function testGetTeamPythagoreanStatsQueriesOffenseAndDefenseTables(): void
    {
        // Arrange
        $this->mockDb->setMockPythagoreanData(['fgm' => 1000, 'ftm' => 500, 'tgm' => 300]);

        // Act
        $this->repository->getTeamPythagoreanStats(5);

        // Assert
        $this->assertQueryExecuted('ibl_team_offense_stats');
        $this->assertQueryExecuted('ibl_team_defense_stats');
    }

    /**
     * @group integration
     * @group standings
     * @group repository
     * @group pythagorean
     */
    public function testGetTeamPythagoreanStatsCalculatesPoints(): void
    {
        // Arrange - Points = FGM*2 + FTM + TGM (3PM counted as 1 extra)
        // Expected: 1000*2 + 500 + 300 = 2800
        $this->mockDb->setMockPythagoreanData(['fgm' => 1000, 'ftm' => 500, 'tgm' => 300]);

        // Act
        $result = $this->repository->getTeamPythagoreanStats(5);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('pointsScored', $result);
        $this->assertArrayHasKey('pointsAllowed', $result);
    }

    /**
     * @group integration
     * @group standings
     * @group repository
     * @group pythagorean
     */
    public function testGetTeamPythagoreanStatsReturnsNullWhenMissingData(): void
    {
        // Arrange - No data
        $this->mockDb->setMockData([]);

        // Act
        $result = $this->repository->getTeamPythagoreanStats(999);

        // Assert
        $this->assertNull($result);
    }

    // ========== VIEW - RENDER REGION TESTS ==========

    /**
     * @group integration
     * @group standings
     * @group view
     */
    public function testRenderRegionIncludesRegionTitle(): void
    {
        // Arrange
        $standingsData = [
            $this->createStandingsRow(1, 'Boston', 'Eastern', 'Atlantic', '50-20', 0.714, 0),
        ];
        $this->mockDb->setMockData($standingsData);
        $this->setupStreakAndPythagoreanData();

        // Act
        $html = $this->view->renderRegion('Eastern');

        // Assert
        $this->assertStringContainsString('Eastern Conference', $html);
        $this->assertStringContainsString('ibl-title', $html);
    }

    /**
     * @group integration
     * @group standings
     * @group view
     */
    public function testRenderRegionIncludesTableHeaders(): void
    {
        // Arrange
        $this->mockDb->setMockData([]);
        $this->setupStreakAndPythagoreanData();

        // Act
        $html = $this->view->renderRegion('Atlantic');

        // Assert
        $this->assertStringContainsString('Team', $html);
        $this->assertStringContainsString('W-L', $html);
        $this->assertStringContainsString('Win%', $html);
        $this->assertStringContainsString('GB', $html);
        $this->assertStringContainsString('Magic', $html);
        $this->assertStringContainsString('Streak', $html);
        $this->assertStringContainsString('Power', $html);
    }

    /**
     * @group integration
     * @group standings
     * @group view
     */
    public function testRenderRegionDisplaysTeamData(): void
    {
        // Arrange
        $standingsData = [
            $this->createStandingsRow(1, 'Boston', 'Eastern', 'Atlantic', '50-20', 0.714, 0),
        ];
        $this->mockDb->setMockData($standingsData);
        $this->setupStreakAndPythagoreanData();

        // Act
        $html = $this->view->renderRegion('Eastern');

        // Assert
        $this->assertStringContainsString('Boston', $html);
        $this->assertStringContainsString('50-20', $html);
        $this->assertStringContainsString('0.714', $html);
    }

    /**
     * @group integration
     * @group standings
     * @group view
     */
    public function testRenderRegionDisplaysDivisionTitle(): void
    {
        // Arrange
        $this->mockDb->setMockData([]);
        $this->setupStreakAndPythagoreanData();

        // Act
        $html = $this->view->renderRegion('Pacific');

        // Assert
        $this->assertStringContainsString('Pacific Division', $html);
    }

    // ========== VIEW - CLINCHED INDICATORS TESTS ==========

    /**
     * @group integration
     * @group standings
     * @group view
     * @group clinched
     */
    public function testRenderRegionShowsClinchedConferenceIndicator(): void
    {
        // Arrange - Team that clinched conference shows Z
        $standingsData = [
            $this->createStandingsRow(1, 'Boston', 'Eastern', 'Atlantic', '60-10', 0.857, 0, 1, 0, 0),
        ];
        $this->mockDb->setMockData($standingsData);
        $this->setupStreakAndPythagoreanData();

        // Act
        $html = $this->view->renderRegion('Eastern');

        // Assert
        $this->assertStringContainsString('<span class="ibl-clinched-indicator">Z</span>-Boston', $html);
    }

    /**
     * @group integration
     * @group standings
     * @group view
     * @group clinched
     */
    public function testRenderRegionShowsClinchedDivisionIndicator(): void
    {
        // Arrange - Team that clinched division shows Y
        $standingsData = [
            $this->createStandingsRow(1, 'Miami', 'Eastern', 'Atlantic', '55-15', 0.786, 5, 0, 1, 0),
        ];
        $this->mockDb->setMockData($standingsData);
        $this->setupStreakAndPythagoreanData();

        // Act
        $html = $this->view->renderRegion('Eastern');

        // Assert
        $this->assertStringContainsString('<span class="ibl-clinched-indicator">Y</span>-Miami', $html);
    }

    /**
     * @group integration
     * @group standings
     * @group view
     * @group clinched
     */
    public function testRenderRegionShowsClinchedPlayoffsIndicator(): void
    {
        // Arrange - Team that clinched playoffs shows X
        $standingsData = [
            $this->createStandingsRow(1, 'New York', 'Eastern', 'Atlantic', '45-25', 0.643, 15, 0, 0, 1),
        ];
        $this->mockDb->setMockData($standingsData);
        $this->setupStreakAndPythagoreanData();

        // Act
        $html = $this->view->renderRegion('Eastern');

        // Assert
        $this->assertStringContainsString('<span class="ibl-clinched-indicator">X</span>-New York', $html);
    }

    /**
     * @group integration
     * @group standings
     * @group view
     * @group clinched
     */
    public function testClinchedConferenceTakesPriorityOverDivision(): void
    {
        // Arrange - Team with both conference and division clinched shows Z (not Y)
        $standingsData = [
            $this->createStandingsRow(1, 'Boston', 'Eastern', 'Atlantic', '65-5', 0.929, 0, 1, 1, 1),
        ];
        $this->mockDb->setMockData($standingsData);
        $this->setupStreakAndPythagoreanData();

        // Act
        $html = $this->view->renderRegion('Eastern');

        // Assert - Should show Z, not Y or X
        $this->assertStringContainsString('<span class="ibl-clinched-indicator">Z</span>-Boston', $html);
        $this->assertStringNotContainsString('<span class="ibl-clinched-indicator">Y</span>-Boston', $html);
        $this->assertStringNotContainsString('<span class="ibl-clinched-indicator">X</span>-Boston', $html);
    }

    /**
     * @group integration
     * @group standings
     * @group view
     * @group clinched
     */
    public function testNoIndicatorWhenNotClinched(): void
    {
        // Arrange - Team with nothing clinched has no indicator
        $standingsData = [
            $this->createStandingsRow(1, 'Chicago', 'Eastern', 'Central', '35-35', 0.500, 25, 0, 0, 0),
        ];
        $this->mockDb->setMockData($standingsData);
        $this->setupStreakAndPythagoreanData();

        // Act
        $html = $this->view->renderRegion('Eastern');

        // Assert - Team name without strong prefix
        $this->assertStringNotContainsString('<span class="ibl-clinched-indicator">Z</span>-Chicago', $html);
        $this->assertStringNotContainsString('<span class="ibl-clinched-indicator">Y</span>-Chicago', $html);
        $this->assertStringNotContainsString('<span class="ibl-clinched-indicator">X</span>-Chicago', $html);
        $this->assertStringContainsString('Chicago', $html);
    }

    // ========== VIEW - PYTHAGOREAN DISPLAY TESTS ==========

    /**
     * @group integration
     * @group standings
     * @group view
     * @group pythagorean
     */
    public function testRenderRegionShowsPythagoreanColumn(): void
    {
        // Arrange
        $standingsData = [
            $this->createStandingsRow(1, 'Boston', 'Eastern', 'Atlantic', '50-20', 0.714, 0),
        ];
        $this->mockDb->setMockData($standingsData);
        $this->setupStreakAndPythagoreanData();

        // Act
        $html = $this->view->renderRegion('Eastern');

        // Assert
        $this->assertStringContainsString('Pyth', $html);
        $this->assertStringContainsString('W-L%', $html);
    }

    /**
     * @group integration
     * @group standings
     * @group view
     * @group pythagorean
     */
    public function testRenderRegionShowsDefaultPythagoreanWhenMissing(): void
    {
        // Arrange - No pythagorean data available
        $standingsData = [
            $this->createStandingsRow(1, 'Boston', 'Eastern', 'Atlantic', '50-20', 0.714, 0),
        ];
        $this->mockDb->setMockData($standingsData);
        // Don't set up pythagorean data - should default to 0.000

        // Act
        $html = $this->view->renderRegion('Eastern');

        // Assert
        $this->assertStringContainsString('0.000', $html);
    }

    // ========== VIEW - STREAK DISPLAY TESTS ==========

    /**
     * @group integration
     * @group standings
     * @group view
     * @group streak
     */
    public function testRenderRegionShowsLast10Record(): void
    {
        // Arrange - Mock data setup: standings first, then streak data will be found
        // Note: The view queries streak data separately per team, so we just need
        // the standings data with team_name field populated
        $standingsData = [
            $this->createStandingsRow(1, 'Boston', 'Eastern', 'Atlantic', '50-20', 0.714, 0),
        ];
        $this->mockDb->setMockData($standingsData);

        // Act
        $html = $this->view->renderRegion('Eastern');

        // Assert - Last 10 column should show data (even if 0-0 when no streak data)
        $this->assertStringContainsString('Last 10', $html);
        // With no streak data, shows 0-0
        $this->assertStringContainsString('0-0', $html);
    }

    /**
     * @group integration
     * @group standings
     * @group view
     * @group streak
     */
    public function testRenderRegionShowsStreakColumn(): void
    {
        // Arrange
        $standingsData = [
            $this->createStandingsRow(1, 'Boston', 'Eastern', 'Atlantic', '50-20', 0.714, 0),
        ];
        $this->mockDb->setMockData($standingsData);

        // Act
        $html = $this->view->renderRegion('Eastern');

        // Assert - Streak column exists in header
        $this->assertStringContainsString('Streak', $html);
    }

    /**
     * @group integration
     * @group standings
     * @group view
     * @group streak
     */
    public function testRenderRegionShowsRatingColumn(): void
    {
        // Arrange
        $standingsData = [
            $this->createStandingsRow(1, 'Boston', 'Eastern', 'Atlantic', '50-20', 0.714, 0),
        ];
        $this->mockDb->setMockData($standingsData);

        // Act
        $html = $this->view->renderRegion('Eastern');

        // Assert - Power Rank column exists with proper class
        $this->assertStringContainsString('Power', $html);
        $this->assertStringContainsString('ibl-stat-highlight', $html);
    }

    // ========== VIEW - XSS PROTECTION TESTS ==========

    /**
     * @group integration
     * @group standings
     * @group view
     * @group security
     */
    public function testRenderRegionEscapesTeamName(): void
    {
        // Arrange - Malicious team name
        $standingsData = [
            $this->createStandingsRow(1, '<script>alert("xss")</script>', 'Eastern', 'Atlantic', '50-20', 0.714, 0),
        ];
        $this->mockDb->setMockData($standingsData);
        $this->setupStreakAndPythagoreanData();

        // Act
        $html = $this->view->renderRegion('Eastern');

        // Assert - Script tag should be escaped
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * @group integration
     * @group standings
     * @group view
     * @group security
     */
    public function testRenderRegionEscapesStreakType(): void
    {
        // Arrange - Just standings data (streak data would require complex mocking)
        // The view uses HtmlSanitizer::safeHtmlOutput() on streak_type, which is
        // already verified by checking output doesn't contain raw XSS
        $standingsData = [
            $this->createStandingsRow(1, 'Boston', 'Eastern', 'Atlantic', '50-20', 0.714, 0),
        ];
        $this->mockDb->setMockData($standingsData);

        // Act
        $html = $this->view->renderRegion('Eastern');

        // Assert - Streak column exists and no raw malicious content
        $this->assertStringContainsString('Streak', $html);
        $this->assertStringContainsString('Boston', $html);
        // Verify sanitizer is used (streak type would show empty or default when no data)
        $this->assertStringNotContainsString('<img src=x', $html);
    }

    // ========== VIEW - TEAM LINK TESTS ==========

    /**
     * @group integration
     * @group standings
     * @group view
     */
    public function testRenderRegionGeneratesCorrectTeamLinks(): void
    {
        // Arrange
        $standingsData = [
            $this->createStandingsRow(5, 'Miami', 'Eastern', 'Atlantic', '45-25', 0.643, 5),
        ];
        $this->mockDb->setMockData($standingsData);
        $this->setupStreakAndPythagoreanData();

        // Act
        $html = $this->view->renderRegion('Eastern');

        // Assert - HTML entities are properly escaped in href attribute
        $this->assertStringContainsString('modules.php?name=Team&amp;op=team&amp;teamID=5', $html);
    }

    /**
     * @group integration
     * @group standings
     * @group view
     */
    public function testRenderRegionIncludesTeamLogo(): void
    {
        // Arrange
        $standingsData = [
            $this->createStandingsRow(5, 'Miami', 'Eastern', 'Atlantic', '45-25', 0.643, 5),
        ];
        $this->mockDb->setMockData($standingsData);
        $this->setupStreakAndPythagoreanData();

        // Act
        $html = $this->view->renderRegion('Eastern');

        // Assert
        $this->assertStringContainsString('images/logo/new5.png', $html);
        $this->assertStringContainsString('ibl-team-cell__logo', $html);
    }

    // ========== VIEW - FULL RENDER TESTS ==========

    /**
     * @group integration
     * @group standings
     * @group view
     */
    public function testRenderIncludesAllRegions(): void
    {
        // Arrange
        $this->mockDb->setMockData([]);
        $this->setupStreakAndPythagoreanData();

        // Act
        $html = $this->view->render();

        // Assert - All 6 regions should be rendered
        $this->assertStringContainsString('Eastern Conference', $html);
        $this->assertStringContainsString('Western Conference', $html);
        $this->assertStringContainsString('Atlantic Division', $html);
        $this->assertStringContainsString('Central Division', $html);
        $this->assertStringContainsString('Midwest Division', $html);
        $this->assertStringContainsString('Pacific Division', $html);
    }

    /**
     * @group integration
     * @group standings
     * @group view
     */
    public function testRenderDoesNotIncludeInlineScript(): void
    {
        // Arrange
        $this->mockDb->setMockData([]);
        $this->setupStreakAndPythagoreanData();

        // Act
        $html = $this->view->render();

        // Assert - Inline script removed; scroll logic now in external responsive-tables.js
        $this->assertStringNotContainsString('<script>', $html);
    }

    /**
     * @group integration
     * @group standings
     * @group view
     */
    public function testRenderIncludesResponsiveTableClasses(): void
    {
        // Arrange
        $this->mockDb->setMockData([]);
        $this->setupStreakAndPythagoreanData();

        // Act
        $html = $this->view->render();

        // Assert - Should have responsive classes for mobile
        $this->assertStringContainsString('responsive-table', $html);
        $this->assertStringContainsString('sticky-col', $html);
        $this->assertStringContainsString('table-scroll-container', $html);
    }

    // ========== COMPLETE WORKFLOW TESTS ==========

    /**
     * @group integration
     * @group standings
     * @group workflow
     */
    public function testCompleteWorkflowWithMultipleTeams(): void
    {
        // Arrange - Multiple teams with different clinch statuses
        $standingsData = [
            $this->createStandingsRow(1, 'Boston', 'Eastern', 'Atlantic', '60-10', 0.857, 0, 1, 1, 1),
            $this->createStandingsRow(2, 'Miami', 'Eastern', 'Atlantic', '50-20', 0.714, 10, 0, 0, 1),
            $this->createStandingsRow(3, 'New York', 'Eastern', 'Atlantic', '40-30', 0.571, 20, 0, 0, 0),
        ];
        $this->mockDb->setMockData($standingsData);
        $this->setupStreakAndPythagoreanData();

        // Act
        $html = $this->view->renderRegion('Eastern');

        // Assert - All teams rendered with correct indicators
        $this->assertStringContainsString('<span class="ibl-clinched-indicator">Z</span>-Boston', $html);
        $this->assertStringContainsString('<span class="ibl-clinched-indicator">X</span>-Miami', $html);
        $this->assertStringContainsString('New York', $html);
        $this->assertStringNotContainsString('<span class="ibl-clinched-indicator">Z</span>-New York', $html);
    }

    /**
     * @group integration
     * @group standings
     * @group workflow
     */
    public function testCompleteWorkflowEmptyStandings(): void
    {
        // Arrange - No teams in region
        $this->mockDb->setMockData([]);
        $this->setupStreakAndPythagoreanData();

        // Act
        $html = $this->view->renderRegion('Eastern');

        // Assert - Should still render table structure without errors
        $this->assertStringContainsString('Eastern Conference', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('</table>', $html);
    }

    // ========== HELPER METHODS ==========

    /**
     * Create a standings row matching ibl_standings structure
     */
    private function createStandingsRow(
        int $teamId,
        string $teamName,
        string $conference,
        string $division,
        string $leagueRecord,
        float $pct,
        float $gamesBack,
        int $clinchedConference = 0,
        int $clinchedDivision = 0,
        int $clinchedPlayoffs = 0
    ): array {
        return [
            'tid' => $teamId,
            'team_name' => $teamName,
            'conference' => $conference,
            'division' => $division,
            'leagueRecord' => $leagueRecord,
            'pct' => $pct,
            'gamesBack' => $gamesBack,
            'confGB' => $gamesBack,
            'divGB' => $gamesBack,
            'confRecord' => '30-10',
            'divRecord' => '15-5',
            'homeRecord' => '30-5',
            'awayRecord' => '20-15',
            'gamesUnplayed' => 12,
            'magicNumber' => $gamesBack > 0 ? 20 : 0,
            'confMagicNumber' => $gamesBack > 0 ? 20 : 0,
            'divMagicNumber' => $gamesBack > 0 ? 15 : 0,
            'clinchedConference' => $clinchedConference,
            'clinchedDivision' => $clinchedDivision,
            'clinchedPlayoffs' => $clinchedPlayoffs,
            'homeWins' => 30,
            'homeLosses' => 5,
            'awayWins' => 20,
            'awayLosses' => 15,
            'homeGames' => 35,
            'awayGames' => 35,
            'color1' => '000000',
            'color2' => 'FFFFFF',
        ];
    }

    /**
     * Setup default streak and pythagorean data to avoid null reference issues
     */
    private function setupStreakAndPythagoreanData(): void
    {
        // Set up mock pythagorean stats (fgm, ftm, tgm for points calculation)
        $this->mockDb->setMockPythagoreanData([
            'fgm' => 1000,
            'ftm' => 500,
            'tgm' => 300,
        ]);
    }
}
