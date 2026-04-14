<?php

declare(strict_types=1);

namespace Tests\Standings;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

use Standings\StandingsRepository;
use Standings\StandingsView;
use Standings\Contracts\StandingsRepositoryInterface;
use Standings\Contracts\StandingsViewInterface;

/**
 * StandingsViewTest - Tests for StandingsView HTML rendering
 *
 * @covers \Standings\StandingsView
 */
#[AllowMockObjectsWithoutExpectations]
class StandingsViewTest extends TestCase
{
    private StandingsViewInterface $view;

    /** @var StandingsRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
    private StandingsRepositoryInterface $mockRepository;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(StandingsRepositoryInterface::class);
        $this->mockRepository->method('getSeriesRecords')->willReturn([]);
        $this->view = new StandingsView($this->mockRepository, 2025);
    }

    /**
     * Create a standard team data array for tests (per-region format with gamesBack/magicNumber aliases)
     *
     * @param array<string, mixed> $overrides Fields to override
     * @return array<string, mixed>
     */
    private function makeTeamData(array $overrides = []): array
    {
        return array_merge([
            'tid' => 1,
            'team_name' => 'Celtics',
            'leagueRecord' => '5-3',
            'pct' => '0.625',
            'gamesBack' => '0.0',
            'magicNumber' => 75,
            'gamesUnplayed' => 74,
            'confRecord' => '3-2',
            'divRecord' => '1-1',
            'homeRecord' => '3-1',
            'awayRecord' => '2-2',
            'homeGames' => 4,
            'awayGames' => 4,
            'clinchedConference' => 0,
            'clinchedDivision' => 0,
            'clinchedPlayoffs' => 0,
            'clinchedLeague' => 0,
            'wins' => 5,
            'color1' => '000000',
            'color2' => 'FFFFFF',
        ], $overrides);
    }

    /**
     * Create a bulk standings row (getAllStandings format with conference/division columns)
     *
     * @param array<string, mixed> $overrides Fields to override
     * @return array<string, mixed>
     */
    private function makeBulkTeamData(array $overrides = []): array
    {
        return array_merge([
            'tid' => 1,
            'team_name' => 'Celtics',
            'leagueRecord' => '5-3',
            'pct' => '0.625',
            'confGB' => '0.0',
            'divGB' => '0.0',
            'confMagicNumber' => 75,
            'divMagicNumber' => 70,
            'gamesUnplayed' => 74,
            'confRecord' => '3-2',
            'divRecord' => '1-1',
            'homeRecord' => '3-1',
            'awayRecord' => '2-2',
            'homeGames' => 4,
            'awayGames' => 4,
            'clinchedConference' => 0,
            'clinchedDivision' => 0,
            'clinchedPlayoffs' => 0,
            'clinchedLeague' => 0,
            'wins' => 5,
            'conference' => 'Eastern',
            'division' => 'Atlantic',
            'color1' => '000000',
            'color2' => 'FFFFFF',
        ], $overrides);
    }

    public function testRenderReturnsString(): void
    {
        $this->mockRepository->method('getAllStandings')->willReturn([]);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->render();

        $this->assertIsString($result);
    }

    public function testRenderIncludesAllConferences(): void
    {
        $this->mockRepository->method('getAllStandings')->willReturn([]);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->render();

        $this->assertStringContainsString('Eastern Conference', $result);
        $this->assertStringContainsString('Western Conference', $result);
    }

    public function testRenderIncludesAllDivisions(): void
    {
        $this->mockRepository->method('getAllStandings')->willReturn([]);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->render();

        $this->assertStringContainsString('Atlantic Division', $result);
        $this->assertStringContainsString('Central Division', $result);
        $this->assertStringContainsString('Midwest Division', $result);
        $this->assertStringContainsString('Pacific Division', $result);
    }

    public function testRenderUsesAllStandingsNotPerRegion(): void
    {
        $this->mockRepository->expects($this->once())
            ->method('getAllStandings')
            ->willReturn([]);
        $this->mockRepository->expects($this->never())
            ->method('getStandingsByRegion');
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $this->view->render();
    }

    public function testRenderGroupsAndDisplaysTeamsCorrectly(): void
    {
        $bulkData = [
            $this->makeBulkTeamData(['tid' => 1, 'team_name' => 'Celtics', 'conference' => 'Eastern', 'division' => 'Atlantic', 'confGB' => '0.0', 'divGB' => '0.0', 'confMagicNumber' => 70, 'divMagicNumber' => 65, 'wins' => 10]),
            $this->makeBulkTeamData(['tid' => 2, 'team_name' => 'Lakers', 'conference' => 'Western', 'division' => 'Pacific', 'confGB' => '0.0', 'divGB' => '0.0', 'confMagicNumber' => 72, 'divMagicNumber' => 68, 'wins' => 8]),
        ];

        $this->mockRepository->method('getAllStandings')->willReturn($bulkData);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->render();

        // Celtics should appear in Eastern Conference and Atlantic Division sections
        $this->assertStringContainsString('Celtics', $result);
        $this->assertStringContainsString('Lakers', $result);
    }

    public function testRenderSortsByGamesBackThenClinchThenWins(): void
    {
        $bulkData = [
            $this->makeBulkTeamData(['tid' => 1, 'team_name' => 'TeamA', 'conference' => 'Eastern', 'division' => 'Atlantic', 'confGB' => '5.0', 'divGB' => '5.0', 'confMagicNumber' => 70, 'divMagicNumber' => 65, 'wins' => 30]),
            $this->makeBulkTeamData(['tid' => 2, 'team_name' => 'TeamB', 'conference' => 'Eastern', 'division' => 'Atlantic', 'confGB' => '0.0', 'divGB' => '0.0', 'confMagicNumber' => 60, 'divMagicNumber' => 55, 'wins' => 35]),
        ];

        $this->mockRepository->method('getAllStandings')->willReturn($bulkData);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->render();

        // TeamB (0.0 GB) should appear before TeamA (5.0 GB)
        $posB = strpos($result, 'TeamB');
        $posA = strpos($result, 'TeamA');
        $this->assertNotFalse($posB);
        $this->assertNotFalse($posA);
        $this->assertLessThan($posA, $posB);
    }

    public function testRenderRegionGeneratesTableHeaders(): void
    {
        $this->mockRepository->method('getStandingsByRegion')->willReturn([]);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringContainsString('<table class="sortable ibl-data-table">', $result);
        $this->assertStringContainsString('Team', $result);
        $this->assertStringContainsString('W-L', $result);
        $this->assertStringContainsString('Win%', $result);
        $this->assertStringContainsString('GB', $result);
        $this->assertStringContainsString('Magic', $result);
    }

    public function testRenderRegionDisplaysTeamData(): void
    {
        $teamData = [$this->makeTeamData()];

        $this->mockRepository->method('getStandingsByRegion')
            ->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')
            ->willReturn([1 => ['last_win' => 5, 'last_loss' => 5, 'streak_type' => 'W', 'streak' => 2, 'ranking' => 10, 'sos' => 0.500, 'remaining_sos' => 0.480, 'sos_rank' => 5, 'remaining_sos_rank' => 8]]);
        $this->mockRepository->method('getAllPythagoreanStats')
            ->willReturn([1 => ['pointsScored' => 2000, 'pointsAllowed' => 1800]]);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringContainsString('Celtics', $result);
        $this->assertStringContainsString('5-3', $result);
        $this->assertStringContainsString('0.625', $result);
        $this->assertStringContainsString('teamID=1', $result);
    }

    public function testRenderRegionIncludesPythagoreanColumn(): void
    {
        $teamData = [$this->makeTeamData()];

        $this->mockRepository->method('getStandingsByRegion')
            ->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')
            ->willReturn([1 => ['last_win' => 5, 'last_loss' => 5, 'streak_type' => 'W', 'streak' => 2, 'ranking' => 10, 'sos' => 0.500, 'remaining_sos' => 0.480, 'sos_rank' => 5, 'remaining_sos_rank' => 8]]);
        $this->mockRepository->method('getAllPythagoreanStats')
            ->willReturn([1 => ['pointsScored' => 2000, 'pointsAllowed' => 1800]]);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringContainsString('Pyth<br>W-L%', $result);
        // The Pythagorean percentage should be present and formatted
        $this->assertMatchesRegularExpression('/\d\.\d{3}/', $result);
    }

    public function testRenderRegionHandlesMissingPythagoreanStats(): void
    {
        $teamData = [$this->makeTeamData()];

        $this->mockRepository->method('getStandingsByRegion')
            ->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')
            ->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')
            ->willReturn([]);

        $result = $this->view->renderRegion('Eastern');

        // Should display 0.000 when stats are missing
        $this->assertStringContainsString('0.000', $result);
    }

    public function testRenderRegionDisplaysClinchedConferenceIndicator(): void
    {
        $teamData = [
            $this->makeTeamData([
                'leagueRecord' => '50-10',
                'pct' => '0.833',
                'magicNumber' => 0,
                'gamesUnplayed' => 22,
                'confRecord' => '30-5',
                'divRecord' => '10-2',
                'homeRecord' => '28-3',
                'awayRecord' => '22-7',
                'homeGames' => 31,
                'awayGames' => 29,
                'clinchedConference' => 1,
                'wins' => 50,
            ]),
        ];

        $this->mockRepository->method('getStandingsByRegion')
            ->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')
            ->willReturn([1 => ['last_win' => 8, 'last_loss' => 2, 'streak_type' => 'W', 'streak' => 5, 'ranking' => 3, 'sos' => 0.550, 'remaining_sos' => 0.520, 'sos_rank' => 3, 'remaining_sos_rank' => 4]]);
        $this->mockRepository->method('getAllPythagoreanStats')
            ->willReturn([1 => ['pointsScored' => 2000, 'pointsAllowed' => 1800]]);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringContainsString('<span class="ibl-clinched-indicator">Z</span>-Celtics', $result);
    }

    public function testRenderRegionDisplaysClinchedDivisionIndicator(): void
    {
        $teamData = [
            $this->makeTeamData([
                'leagueRecord' => '45-15',
                'pct' => '0.750',
                'magicNumber' => 5,
                'gamesUnplayed' => 22,
                'confRecord' => '28-7',
                'divRecord' => '12-2',
                'homeRecord' => '25-6',
                'awayRecord' => '20-9',
                'homeGames' => 31,
                'awayGames' => 29,
                'clinchedDivision' => 1,
                'wins' => 45,
            ]),
        ];

        $this->mockRepository->method('getStandingsByRegion')
            ->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')
            ->willReturn([1 => ['last_win' => 7, 'last_loss' => 3, 'streak_type' => 'W', 'streak' => 3, 'ranking' => 5, 'sos' => 0.480, 'remaining_sos' => 0.510, 'sos_rank' => 7, 'remaining_sos_rank' => 6]]);
        $this->mockRepository->method('getAllPythagoreanStats')
            ->willReturn([1 => ['pointsScored' => 2000, 'pointsAllowed' => 1800]]);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringContainsString('<span class="ibl-clinched-indicator">Y</span>-Celtics', $result);
    }

    public function testRenderRegionDisplaysClinchedPlayoffsIndicator(): void
    {
        $teamData = [
            $this->makeTeamData([
                'leagueRecord' => '40-20',
                'pct' => '0.667',
                'gamesBack' => '5.0',
                'magicNumber' => 10,
                'gamesUnplayed' => 22,
                'confRecord' => '25-10',
                'divRecord' => '8-4',
                'homeRecord' => '22-9',
                'awayRecord' => '18-11',
                'homeGames' => 31,
                'awayGames' => 29,
                'clinchedPlayoffs' => 1,
                'wins' => 40,
            ]),
        ];

        $this->mockRepository->method('getStandingsByRegion')
            ->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')
            ->willReturn([1 => ['last_win' => 6, 'last_loss' => 4, 'streak_type' => 'L', 'streak' => 1, 'ranking' => 15, 'sos' => 0.450, 'remaining_sos' => 0.490, 'sos_rank' => 10, 'remaining_sos_rank' => 9]]);
        $this->mockRepository->method('getAllPythagoreanStats')
            ->willReturn([1 => ['pointsScored' => 2000, 'pointsAllowed' => 1800]]);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringContainsString('<span class="ibl-clinched-indicator">X</span>-Celtics', $result);
    }

    public function testRenderRegionHandlesMissingStreakData(): void
    {
        $teamData = [$this->makeTeamData()];

        $this->mockRepository->method('getStandingsByRegion')
            ->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')
            ->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')
            ->willReturn([1 => ['pointsScored' => 2000, 'pointsAllowed' => 1800]]);

        $result = $this->view->renderRegion('Eastern');

        // Should not throw an error; should display 0-0 for last 10
        $this->assertStringContainsString('0-0', $result);
    }

    public function testRenderRegionDisplaysSosColumns(): void
    {
        $teamData = [$this->makeTeamData()];

        $this->mockRepository->method('getStandingsByRegion')
            ->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')
            ->willReturn([1 => ['last_win' => 5, 'last_loss' => 5, 'streak_type' => 'W', 'streak' => 2, 'ranking' => 10, 'sos' => 0.523, 'remaining_sos' => 0.471, 'sos_rank' => 4, 'remaining_sos_rank' => 12]]);
        $this->mockRepository->method('getAllPythagoreanStats')
            ->willReturn([1 => ['pointsScored' => 2000, 'pointsAllowed' => 1800]]);

        $result = $this->view->renderRegion('Eastern');

        // Header columns
        $this->assertStringContainsString('SOS', $result);
        // Data values (3 decimal places)
        $this->assertStringContainsString('0.523', $result);
        $this->assertStringContainsString('0.471', $result);
    }

    public function testRenderRegionEscapesTeamName(): void
    {
        $teamData = [
            $this->makeTeamData(['team_name' => '<script>alert("xss")</script>']),
        ];

        $this->mockRepository->method('getStandingsByRegion')
            ->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')
            ->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')
            ->willReturn([]);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    // ========== LEAGUE CLINCH (W) INDICATOR TESTS ==========

    public function testRenderRegionDisplaysClinchedLeagueIndicator(): void
    {
        $teamData = [
            $this->makeTeamData([
                'leagueRecord' => '72-8',
                'pct' => '0.900',
                'wins' => 72,
                'gamesUnplayed' => 2,
                'clinchedLeague' => 1,
                'clinchedConference' => 1,
                'clinchedDivision' => 1,
                'clinchedPlayoffs' => 1,
            ]),
        ];

        $this->mockRepository->method('getStandingsByRegion')
            ->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')
            ->willReturn([1 => ['last_win' => 9, 'last_loss' => 1, 'streak_type' => 'W', 'streak' => 10, 'ranking' => 1, 'sos' => 0.500, 'remaining_sos' => 0.500, 'sos_rank' => 1, 'remaining_sos_rank' => 1]]);
        $this->mockRepository->method('getAllPythagoreanStats')
            ->willReturn([1 => ['pointsScored' => 2000, 'pointsAllowed' => 1800]]);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringContainsString('<span class="ibl-clinched-indicator">W</span>-Celtics', $result);
    }

    public function testWIndicatorTakesPriorityOverAllOthers(): void
    {
        $teamData = [
            $this->makeTeamData([
                'wins' => 70,
                'clinchedLeague' => 1,
                'clinchedConference' => 1,
                'clinchedDivision' => 1,
                'clinchedPlayoffs' => 1,
            ]),
        ];

        $this->mockRepository->method('getStandingsByRegion')
            ->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringContainsString('<span class="ibl-clinched-indicator">W</span>-Celtics', $result);
        $this->assertStringNotContainsString('<span class="ibl-clinched-indicator">Z</span>', $result);
        $this->assertStringNotContainsString('<span class="ibl-clinched-indicator">Y</span>', $result);
        $this->assertStringNotContainsString('<span class="ibl-clinched-indicator">X</span>', $result);
    }

    // ========== CLINCH TIER CSS CLASS TESTS ==========

    public function testClinchLeagueAppliesCorrectCssClass(): void
    {
        $teamData = [
            $this->makeTeamData(['clinchedLeague' => 1, 'wins' => 70]),
        ];

        $this->mockRepository->method('getStandingsByRegion')->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringContainsString('class="clinch-league"', $result);
    }

    public function testClinchConferenceAppliesCorrectCssClass(): void
    {
        $teamData = [
            $this->makeTeamData(['clinchedConference' => 1, 'wins' => 50]),
        ];

        $this->mockRepository->method('getStandingsByRegion')->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringContainsString('class="clinch-conference"', $result);
    }

    public function testClinchDivisionAppliesCorrectCssClass(): void
    {
        $teamData = [
            $this->makeTeamData(['clinchedDivision' => 1, 'wins' => 45]),
        ];

        $this->mockRepository->method('getStandingsByRegion')->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringContainsString('class="clinch-division"', $result);
    }

    public function testClinchPlayoffsAppliesCorrectCssClass(): void
    {
        $teamData = [
            $this->makeTeamData(['clinchedPlayoffs' => 1, 'wins' => 40]),
        ];

        $this->mockRepository->method('getStandingsByRegion')->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringContainsString('class="clinch-playoffs"', $result);
    }

    // ========== BOTTOM-LOCKED TESTS ==========

    public function testBottomLockedClassAppliedWhenTeamCantCatchUp(): void
    {
        $teamData = [
            $this->makeTeamData(['tid' => 1, 'team_name' => 'First', 'wins' => 50, 'gamesUnplayed' => 10, 'gamesBack' => '0.0']),
            $this->makeTeamData(['tid' => 2, 'team_name' => 'Last', 'wins' => 30, 'gamesUnplayed' => 5, 'gamesBack' => '20.0']),
        ];

        $this->mockRepository->method('getStandingsByRegion')->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->renderRegion('Eastern');

        // Last team: 30 wins + 5 games left = 35 max, can't catch 50. Should be bottom-locked.
        $this->assertStringContainsString('class="bottom-locked"', $result);
    }

    public function testNoBottomLockWhenTeamCanStillCatchUp(): void
    {
        $teamData = [
            $this->makeTeamData(['tid' => 1, 'team_name' => 'First', 'wins' => 40, 'gamesUnplayed' => 20, 'gamesBack' => '0.0']),
            $this->makeTeamData(['tid' => 2, 'team_name' => 'Second', 'wins' => 35, 'gamesUnplayed' => 20, 'gamesBack' => '5.0']),
        ];

        $this->mockRepository->method('getStandingsByRegion')->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->renderRegion('Eastern');

        // Second team: 35 + 20 = 55 max, can catch 40. Not locked.
        $this->assertStringNotContainsString('bottom-locked', $result);
    }

    public function testBottomLockCascadesFromBottom(): void
    {
        $teamData = [
            $this->makeTeamData(['tid' => 1, 'team_name' => 'First', 'wins' => 60, 'gamesUnplayed' => 5, 'gamesBack' => '0.0']),
            $this->makeTeamData(['tid' => 2, 'team_name' => 'Second', 'wins' => 50, 'gamesUnplayed' => 5, 'gamesBack' => '10.0']),
            $this->makeTeamData(['tid' => 3, 'team_name' => 'Third', 'wins' => 30, 'gamesUnplayed' => 5, 'gamesBack' => '30.0']),
            $this->makeTeamData(['tid' => 4, 'team_name' => 'Fourth', 'wins' => 20, 'gamesUnplayed' => 5, 'gamesBack' => '40.0']),
        ];

        $this->mockRepository->method('getStandingsByRegion')->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->renderRegion('Eastern');

        // Fourth: 20+5=25 < 30 (Third's wins) → locked
        // Third: 30+5=35 < 50 (Second's wins) → locked
        // Second: 50+5=55 < 60 (First's wins) → locked
        // All three bottom teams should be locked
        $this->assertSame(3, substr_count($result, 'class="bottom-locked"'));
    }

    public function testBottomLockStopsWhenTeamCanCatch(): void
    {
        $teamData = [
            $this->makeTeamData(['tid' => 1, 'team_name' => 'First', 'wins' => 50, 'gamesUnplayed' => 10, 'gamesBack' => '0.0']),
            $this->makeTeamData(['tid' => 2, 'team_name' => 'Second', 'wins' => 45, 'gamesUnplayed' => 10, 'gamesBack' => '5.0']),
            $this->makeTeamData(['tid' => 3, 'team_name' => 'Third', 'wins' => 20, 'gamesUnplayed' => 5, 'gamesBack' => '30.0']),
        ];

        $this->mockRepository->method('getStandingsByRegion')->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->renderRegion('Eastern');

        // Third: 20+5=25 < 45 → locked
        // Second: 45+10=55 >= 50 → NOT locked (breaks cascade)
        // Only Third is locked
        $this->assertSame(1, substr_count($result, 'class="bottom-locked"'));
    }

    // ========== SEASON-OVER ELIMINATION TESTS ==========

    public function testSeasonOverUnclinkedTeamsAreBottomLocked(): void
    {
        $teamData = [
            $this->makeTeamData(['tid' => 1, 'team_name' => 'First', 'wins' => 58, 'gamesUnplayed' => 0, 'gamesBack' => '0.0', 'clinchedPlayoffs' => 1]),
            $this->makeTeamData(['tid' => 2, 'team_name' => 'Second', 'wins' => 50, 'gamesUnplayed' => 0, 'gamesBack' => '8.0', 'clinchedPlayoffs' => 1]),
            $this->makeTeamData(['tid' => 3, 'team_name' => 'Third', 'wins' => 36, 'gamesUnplayed' => 0, 'gamesBack' => '22.0']),
            $this->makeTeamData(['tid' => 4, 'team_name' => 'Fourth', 'wins' => 20, 'gamesUnplayed' => 0, 'gamesBack' => '38.0']),
        ];

        $this->mockRepository->method('getStandingsByRegion')->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->renderRegion('Eastern');

        // Season over: Third and Fourth have no clinch flag → bottom-locked
        $this->assertSame(2, substr_count($result, 'class="bottom-locked"'));
        // First and Second have clinch flags → clinch-playoffs
        $this->assertSame(2, substr_count($result, 'class="clinch-playoffs"'));
    }

    public function testSeasonOverClinkedTeamsNeverBottomLocked(): void
    {
        $teamData = [
            $this->makeTeamData(['tid' => 1, 'team_name' => 'First', 'wins' => 58, 'gamesUnplayed' => 0, 'gamesBack' => '0.0', 'clinchedDivision' => 1]),
            $this->makeTeamData(['tid' => 2, 'team_name' => 'Second', 'wins' => 55, 'gamesUnplayed' => 0, 'gamesBack' => '3.0', 'clinchedPlayoffs' => 1]),
            $this->makeTeamData(['tid' => 3, 'team_name' => 'Third', 'wins' => 53, 'gamesUnplayed' => 0, 'gamesBack' => '5.0', 'clinchedPlayoffs' => 1]),
        ];

        $this->mockRepository->method('getStandingsByRegion')->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->renderRegion('Eastern');

        // All clinched → none bottom-locked
        $this->assertStringNotContainsString('bottom-locked', $result);
    }

    public function testSeasonOverTiedEliminatedTeamsAllBottomLocked(): void
    {
        $teamData = [
            $this->makeTeamData(['tid' => 1, 'team_name' => 'First', 'wins' => 45, 'gamesUnplayed' => 0, 'gamesBack' => '0.0', 'clinchedPlayoffs' => 1]),
            $this->makeTeamData(['tid' => 2, 'team_name' => 'Second', 'wins' => 35, 'gamesUnplayed' => 0, 'gamesBack' => '10.0']),
            $this->makeTeamData(['tid' => 3, 'team_name' => 'Third', 'wins' => 35, 'gamesUnplayed' => 0, 'gamesBack' => '10.0']),
            $this->makeTeamData(['tid' => 4, 'team_name' => 'Fourth', 'wins' => 35, 'gamesUnplayed' => 0, 'gamesBack' => '10.0']),
        ];

        $this->mockRepository->method('getStandingsByRegion')->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->renderRegion('Eastern');

        // All three tied teams without clinch flags → bottom-locked
        $this->assertSame(3, substr_count($result, 'class="bottom-locked"'));
    }

    public function testMidSeasonCascadeStopsAtClinchedTeam(): void
    {
        $teamData = [
            $this->makeTeamData(['tid' => 1, 'team_name' => 'First', 'wins' => 60, 'gamesUnplayed' => 5, 'gamesBack' => '0.0', 'clinchedPlayoffs' => 1]),
            $this->makeTeamData(['tid' => 2, 'team_name' => 'Second', 'wins' => 50, 'gamesUnplayed' => 5, 'gamesBack' => '10.0', 'clinchedPlayoffs' => 1]),
            $this->makeTeamData(['tid' => 3, 'team_name' => 'Third', 'wins' => 20, 'gamesUnplayed' => 5, 'gamesBack' => '40.0']),
            $this->makeTeamData(['tid' => 4, 'team_name' => 'Fourth', 'wins' => 10, 'gamesUnplayed' => 5, 'gamesBack' => '50.0']),
        ];

        $this->mockRepository->method('getStandingsByRegion')->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->renderRegion('Eastern');

        // Fourth: 10+5=15 < 20 → locked
        // Third: 20+5=25 < 50 → locked
        // Second: clinched → cascade stops
        $this->assertSame(2, substr_count($result, 'class="bottom-locked"'));
    }

    // ========== HEAD-TO-HEAD TIE-BREAKING TESTS ==========

    /**
     * Create a fresh mock repository with H2H series records
     *
     * @param list<array<string, mixed>> $teamData
     * @param list<array{self: int, opponent: int, wins: int, losses: int}> $seriesRecords
     * @return StandingsRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createMockWithH2H(array $teamData, array $seriesRecords): StandingsRepositoryInterface
    {
        $mock = $this->createMock(StandingsRepositoryInterface::class);
        $mock->method('getStandingsByRegion')->willReturn($teamData);
        $mock->method('getAllStreakData')->willReturn([]);
        $mock->method('getAllPythagoreanStats')->willReturn([]);
        $mock->method('getSeriesRecords')->willReturn($seriesRecords);

        return $mock;
    }

    public function testTwoTeamH2HTiebreakerSortsWinnerFirst(): void
    {
        // Grizzlies (tid=10) and Lakers (tid=11) tied on GB/clinch/wins
        // Grizzlies are 3-2 vs Lakers in H2H
        $teamData = [
            $this->makeTeamData(['tid' => 11, 'team_name' => 'Lakers', 'wins' => 40, 'gamesBack' => '5.0']),
            $this->makeTeamData(['tid' => 10, 'team_name' => 'Grizzlies', 'wins' => 40, 'gamesBack' => '5.0']),
        ];

        $seriesRecords = [
            ['self' => 10, 'opponent' => 11, 'wins' => 3, 'losses' => 2],
            ['self' => 11, 'opponent' => 10, 'wins' => 2, 'losses' => 3],
        ];

        $mock = $this->createMockWithH2H($teamData, $seriesRecords);
        $view = new StandingsView($mock, 2025);
        $result = $view->renderRegion('Western');

        // Grizzlies (3-2 H2H) should appear before Lakers (2-3 H2H)
        $grizzPos = strpos($result, 'Grizzlies');
        $lakersPos = strpos($result, 'Lakers');
        $this->assertIsInt($grizzPos);
        $this->assertIsInt($lakersPos);
        $this->assertLessThan($lakersPos, $grizzPos, 'Grizzlies (3-2 H2H) should appear before Lakers');
    }

    public function testThreeTeamAggregateH2HTiebreaker(): void
    {
        // Three-way tie: Mavericks (6-3 aggregate), Warriors (4-3), Jazz (2-6)
        $teamData = [
            $this->makeTeamData(['tid' => 20, 'team_name' => 'Jazz', 'wins' => 38, 'gamesBack' => '7.0']),
            $this->makeTeamData(['tid' => 21, 'team_name' => 'Warriors', 'wins' => 38, 'gamesBack' => '7.0']),
            $this->makeTeamData(['tid' => 22, 'team_name' => 'Mavericks', 'wins' => 38, 'gamesBack' => '7.0']),
        ];

        $seriesRecords = [
            // Mavericks vs Jazz: 4-1
            ['self' => 22, 'opponent' => 20, 'wins' => 4, 'losses' => 1],
            ['self' => 20, 'opponent' => 22, 'wins' => 1, 'losses' => 4],
            // Mavericks vs Warriors: 2-2
            ['self' => 22, 'opponent' => 21, 'wins' => 2, 'losses' => 2],
            ['self' => 21, 'opponent' => 22, 'wins' => 2, 'losses' => 2],
            // Warriors vs Jazz: 2-1
            ['self' => 21, 'opponent' => 20, 'wins' => 2, 'losses' => 1],
            ['self' => 20, 'opponent' => 21, 'wins' => 1, 'losses' => 2],
        ];

        $mock = $this->createMockWithH2H($teamData, $seriesRecords);
        $view = new StandingsView($mock, 2025);
        $result = $view->renderRegion('Midwest');

        // Mavericks (6-3, .667) > Warriors (4-3, .571) > Jazz (2-6, .250)
        $mavsPos = strpos($result, 'Mavericks');
        $warriorsPos = strpos($result, 'Warriors');
        $jazzPos = strpos($result, 'Jazz');
        $this->assertIsInt($mavsPos);
        $this->assertIsInt($warriorsPos);
        $this->assertIsInt($jazzPos);
        $this->assertLessThan($warriorsPos, $mavsPos, 'Mavericks should be first (6-3 aggregate H2H)');
        $this->assertLessThan($jazzPos, $warriorsPos, 'Warriors should be second (4-3 aggregate H2H)');
    }

    public function testH2HDoesNotOverrideGamesBackDifference(): void
    {
        // Team A has better GB than Team B, but worse H2H
        $teamData = [
            $this->makeTeamData(['tid' => 30, 'team_name' => 'TeamA', 'wins' => 45, 'gamesBack' => '0.0']),
            $this->makeTeamData(['tid' => 31, 'team_name' => 'TeamB', 'wins' => 40, 'gamesBack' => '5.0']),
        ];

        $seriesRecords = [
            ['self' => 31, 'opponent' => 30, 'wins' => 4, 'losses' => 1],
            ['self' => 30, 'opponent' => 31, 'wins' => 1, 'losses' => 4],
        ];

        $mock = $this->createMockWithH2H($teamData, $seriesRecords);
        $view = new StandingsView($mock, 2025);
        $result = $view->renderRegion('Eastern');

        // TeamA should still be first despite worse H2H (different GB)
        $teamAPos = strpos($result, 'TeamA');
        $teamBPos = strpos($result, 'TeamB');
        $this->assertIsInt($teamAPos);
        $this->assertIsInt($teamBPos);
        $this->assertLessThan($teamBPos, $teamAPos, 'GB difference should not be overridden by H2H');
    }

    public function testEqualH2HPreservesWinsOrder(): void
    {
        // Teams split series evenly — original sort (by wins) should hold
        $teamData = [
            $this->makeTeamData(['tid' => 40, 'team_name' => 'HighWins', 'wins' => 42, 'gamesBack' => '3.0']),
            $this->makeTeamData(['tid' => 41, 'team_name' => 'LowWins', 'wins' => 42, 'gamesBack' => '3.0']),
        ];

        $seriesRecords = [
            ['self' => 40, 'opponent' => 41, 'wins' => 2, 'losses' => 2],
            ['self' => 41, 'opponent' => 40, 'wins' => 2, 'losses' => 2],
        ];

        $mock = $this->createMockWithH2H($teamData, $seriesRecords);
        $view = new StandingsView($mock, 2025);
        $result = $view->renderRegion('Eastern');

        // Both have equal H2H (.500), so original order preserved
        $highPos = strpos($result, 'HighWins');
        $lowPos = strpos($result, 'LowWins');
        $this->assertIsInt($highPos);
        $this->assertIsInt($lowPos);
        $this->assertLessThan($lowPos, $highPos, 'Equal H2H should preserve original order');
    }

    public function testNoGamesPlayedPreservesOrder(): void
    {
        // Tied teams with no H2H games at all
        $teamData = [
            $this->makeTeamData(['tid' => 50, 'team_name' => 'FirstTeam', 'wins' => 35, 'gamesBack' => '10.0']),
            $this->makeTeamData(['tid' => 51, 'team_name' => 'SecondTeam', 'wins' => 35, 'gamesBack' => '10.0']),
        ];

        // No series records between these teams
        $this->mockRepository->method('getStandingsByRegion')->willReturn($teamData);
        $this->mockRepository->method('getAllStreakData')->willReturn([]);
        $this->mockRepository->method('getAllPythagoreanStats')->willReturn([]);

        $result = $this->view->renderRegion('Eastern');

        // No H2H data → original order preserved
        $firstPos = strpos($result, 'FirstTeam');
        $secondPos = strpos($result, 'SecondTeam');
        $this->assertIsInt($firstPos);
        $this->assertIsInt($secondPos);
        $this->assertLessThan($secondPos, $firstPos, 'No H2H games should preserve original order');
    }

    public function testRenderRegionAppliesH2HTiebreaker(): void
    {
        // Verify the renderRegion path (not just render) applies H2H
        $teamData = [
            $this->makeTeamData(['tid' => 60, 'team_name' => 'Loser', 'wins' => 40, 'gamesBack' => '5.0']),
            $this->makeTeamData(['tid' => 61, 'team_name' => 'Winner', 'wins' => 40, 'gamesBack' => '5.0']),
        ];

        $seriesRecords = [
            ['self' => 61, 'opponent' => 60, 'wins' => 3, 'losses' => 1],
            ['self' => 60, 'opponent' => 61, 'wins' => 1, 'losses' => 3],
        ];

        $mock = $this->createMockWithH2H($teamData, $seriesRecords);
        $view = new StandingsView($mock, 2025);
        $result = $view->renderRegion('Atlantic');

        $winnerPos = strpos($result, 'Winner');
        $loserPos = strpos($result, 'Loser');
        $this->assertIsInt($winnerPos);
        $this->assertIsInt($loserPos);
        $this->assertLessThan($loserPos, $winnerPos, 'renderRegion should apply H2H tie-breaking');
    }
}
