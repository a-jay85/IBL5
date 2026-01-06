<?php

declare(strict_types=1);

namespace Tests\Standings;

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
class StandingsViewTest extends TestCase
{
    private StandingsViewInterface $view;
    
    /** @var StandingsRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
    private StandingsRepositoryInterface $mockRepository;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(StandingsRepositoryInterface::class);
        $this->view = new StandingsView($this->mockRepository);
    }

    public function testRenderReturnsString(): void
    {
        $this->mockRepository->method('getStandingsByRegion')->willReturn([]);
        $this->mockRepository->method('getTeamStreakData')->willReturn(null);

        $result = $this->view->render();

        $this->assertIsString($result);
    }

    public function testRenderIncludesSorttableScript(): void
    {
        $this->mockRepository->method('getStandingsByRegion')->willReturn([]);
        $this->mockRepository->method('getTeamStreakData')->willReturn(null);

        $result = $this->view->render();

        $this->assertStringContainsString('<script src="sorttable.js"></script>', $result);
    }

    public function testRenderIncludesAllConferences(): void
    {
        $this->mockRepository->method('getStandingsByRegion')->willReturn([]);
        $this->mockRepository->method('getTeamStreakData')->willReturn(null);

        $result = $this->view->render();

        $this->assertStringContainsString('Eastern Conference', $result);
        $this->assertStringContainsString('Western Conference', $result);
    }

    public function testRenderIncludesAllDivisions(): void
    {
        $this->mockRepository->method('getStandingsByRegion')->willReturn([]);
        $this->mockRepository->method('getTeamStreakData')->willReturn(null);

        $result = $this->view->render();

        $this->assertStringContainsString('Atlantic Division', $result);
        $this->assertStringContainsString('Central Division', $result);
        $this->assertStringContainsString('Midwest Division', $result);
        $this->assertStringContainsString('Pacific Division', $result);
    }

    public function testRenderRegionGeneratesTableHeaders(): void
    {
        $this->mockRepository->method('getStandingsByRegion')->willReturn([]);
        $this->mockRepository->method('getTeamStreakData')->willReturn(null);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringContainsString('<table class="sortable">', $result);
        $this->assertStringContainsString('Team', $result);
        $this->assertStringContainsString('W-L', $result);
        $this->assertStringContainsString('Pct', $result);
        $this->assertStringContainsString('GB', $result);
        $this->assertStringContainsString('Magic#', $result);
    }

    public function testRenderRegionDisplaysTeamData(): void
    {
        $teamData = [
            [
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
            ],
        ];

        $this->mockRepository->method('getStandingsByRegion')
            ->willReturn($teamData);
        $this->mockRepository->method('getTeamStreakData')
            ->willReturn(['last_win' => 5, 'last_loss' => 5, 'streak_type' => 'W', 'streak' => 2]);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringContainsString('Celtics', $result);
        $this->assertStringContainsString('5-3', $result);
        $this->assertStringContainsString('0.625', $result);
        $this->assertStringContainsString('teamID=1', $result);
    }

    public function testRenderRegionDisplaysClinchedConferenceIndicator(): void
    {
        $teamData = [
            [
                'tid' => 1,
                'team_name' => 'Celtics',
                'leagueRecord' => '50-10',
                'pct' => '0.833',
                'gamesBack' => '0.0',
                'magicNumber' => 0,
                'gamesUnplayed' => 22,
                'confRecord' => '30-5',
                'divRecord' => '10-2',
                'homeRecord' => '28-3',
                'awayRecord' => '22-7',
                'homeGames' => 31,
                'awayGames' => 29,
                'clinchedConference' => 1,
                'clinchedDivision' => 0,
                'clinchedPlayoffs' => 0,
            ],
        ];

        $this->mockRepository->method('getStandingsByRegion')
            ->willReturn($teamData);
        $this->mockRepository->method('getTeamStreakData')
            ->willReturn(['last_win' => 8, 'last_loss' => 2, 'streak_type' => 'W', 'streak' => 5]);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringContainsString('<b>Z</b>-Celtics', $result);
    }

    public function testRenderRegionDisplaysClinchedDivisionIndicator(): void
    {
        $teamData = [
            [
                'tid' => 1,
                'team_name' => 'Celtics',
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
                'clinchedPlayoffs' => 0,
            ],
        ];

        $this->mockRepository->method('getStandingsByRegion')
            ->willReturn($teamData);
        $this->mockRepository->method('getTeamStreakData')
            ->willReturn(['last_win' => 7, 'last_loss' => 3, 'streak_type' => 'W', 'streak' => 3]);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringContainsString('<b>Y</b>-Celtics', $result);
    }

    public function testRenderRegionDisplaysClinchedPlayoffsIndicator(): void
    {
        $teamData = [
            [
                'tid' => 1,
                'team_name' => 'Celtics',
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
                'clinchedConference' => 0,
                'clinchedDivision' => 0,
                'clinchedPlayoffs' => 1,
            ],
        ];

        $this->mockRepository->method('getStandingsByRegion')
            ->willReturn($teamData);
        $this->mockRepository->method('getTeamStreakData')
            ->willReturn(['last_win' => 6, 'last_loss' => 4, 'streak_type' => 'L', 'streak' => 1]);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringContainsString('<b>X</b>-Celtics', $result);
    }

    public function testRenderRegionHandlesMissingStreakData(): void
    {
        $teamData = [
            [
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
            ],
        ];

        $this->mockRepository->method('getStandingsByRegion')
            ->willReturn($teamData);
        $this->mockRepository->method('getTeamStreakData')
            ->willReturn(null);

        $result = $this->view->renderRegion('Eastern');

        // Should not throw an error; should display 0-0 for last 10
        $this->assertStringContainsString('0-0', $result);
    }

    public function testRenderRegionEscapesTeamName(): void
    {
        $teamData = [
            [
                'tid' => 1,
                'team_name' => '<script>alert("xss")</script>',
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
            ],
        ];

        $this->mockRepository->method('getStandingsByRegion')
            ->willReturn($teamData);
        $this->mockRepository->method('getTeamStreakData')
            ->willReturn(null);

        $result = $this->view->renderRegion('Eastern');

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }
}
