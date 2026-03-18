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
}
