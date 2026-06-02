<?php

declare(strict_types=1);

namespace Tests\LeagueSchedule;

use LeagueSchedule\Contracts\LeagueScheduleRepositoryInterface;
use LeagueSchedule\LeagueScheduleService;
use LeagueSchedule\Contracts\LeagueScheduleServiceInterface;
use League\League;
use PHPUnit\Framework\TestCase;
use Season\Season;

class LeagueScheduleServiceTest extends TestCase
{
    public function testGetSchedulePageDataReturnsExpectedStructure(): void
    {
        $mockRepo = self::createStub(LeagueScheduleRepositoryInterface::class);
        $mockRepo->method('getAllGamesWithBoxScoreInfo')->willReturn([]);
        $mockRepo->method('getTeamRecords')->willReturn([]);

        $season = self::createStub(Season::class);
        $season->projectedNextSimEndDate = new \DateTime('2025-11-15');
        $season->phase = 'Regular Season';

        $league = self::createStub(League::class);
        $league->method('getSimLengthInDays')->willReturn(7);

        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);

        $service = new LeagueScheduleService($mockRepo);
        $result = $service->getSchedulePageData($season, $league, $commonRepo);

        $this->assertArrayHasKey('gamesByMonth', $result);
        $this->assertArrayHasKey('firstUnplayedId', $result);
        $this->assertArrayHasKey('isPlayoffPhase', $result);
        $this->assertArrayHasKey('playoffMonthKey', $result);
        $this->assertArrayHasKey('simLengthDays', $result);
        $this->assertSame([], $result['gamesByMonth']);
        $this->assertNull($result['firstUnplayedId']);
        $this->assertFalse($result['isPlayoffPhase']);
        $this->assertNull($result['playoffMonthKey']);
        $this->assertSame(7, $result['simLengthDays']);
    }

    public function testOrganizesGamesByMonth(): void
    {
        $mockRepo = self::createStub(LeagueScheduleRepositoryInterface::class);
        $mockRepo->method('getAllGamesWithBoxScoreInfo')->willReturn([
            [
                'id' => 1,
                'game_date' => '2025-11-01',
                'visitor_teamid' => 1,
                'visitor_score' => 100,
                'home_teamid' => 2,
                'home_score' => 95,
                'box_id' => 101,
                'game_of_that_day' => 1,
            ],
            [
                'id' => 2,
                'game_date' => '2025-12-01',
                'visitor_teamid' => 3,
                'visitor_score' => 110,
                'home_teamid' => 4,
                'home_score' => 105,
                'box_id' => 102,
                'game_of_that_day' => 1,
            ],
        ]);
        $mockRepo->method('getTeamRecords')->willReturn([
            1 => '25-10',
            2 => '20-15',
            3 => '22-13',
            4 => '18-17',
        ]);

        $season = self::createStub(Season::class);
        $season->projectedNextSimEndDate = new \DateTime('2025-10-15');
        $season->phase = 'Regular Season';

        $league = self::createStub(League::class);
        $league->method('getSimLengthInDays')->willReturn(7);

        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTeamnameFromTeamID')->willReturnMap([
            [1, 'Team A'],
            [2, 'Team B'],
            [3, 'Team C'],
            [4, 'Team D'],
        ]);

        $service = new LeagueScheduleService($mockRepo);
        $result = $service->getSchedulePageData($season, $league, $commonRepo);

        $this->assertArrayHasKey('2025-11', $result['gamesByMonth']);
        $this->assertArrayHasKey('2025-12', $result['gamesByMonth']);
        $this->assertSame('November', $result['gamesByMonth']['2025-11']['label']);
        $this->assertSame('December', $result['gamesByMonth']['2025-12']['label']);
    }

    public function testIdentifiesUpcomingGames(): void
    {
        $mockRepo = self::createStub(LeagueScheduleRepositoryInterface::class);
        $mockRepo->method('getAllGamesWithBoxScoreInfo')->willReturn([
            [
                'id' => 1,
                'game_date' => '2025-11-10',
                'visitor_teamid' => 1,
                'visitor_score' => 0,
                'home_teamid' => 2,
                'home_score' => 0,
                'box_id' => 101,
                'game_of_that_day' => 0,
            ],
        ]);
        $mockRepo->method('getTeamRecords')->willReturn([]);

        $season = self::createStub(Season::class);
        $season->projectedNextSimEndDate = new \DateTime('2025-11-15');
        $season->phase = 'Regular Season';

        $league = self::createStub(League::class);
        $league->method('getSimLengthInDays')->willReturn(7);

        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTeamnameFromTeamID')->willReturn('Team');

        $service = new LeagueScheduleService($mockRepo);
        $result = $service->getSchedulePageData($season, $league, $commonRepo);

        $games = $result['gamesByMonth']['2025-11']['dates']['2025-11-10'];
        $this->assertTrue($games[0]['isUpcoming']);
        $this->assertTrue($games[0]['isUnplayed']);
        $this->assertSame('game-101', $result['firstUnplayedId']);
    }

    public function testPlayoffPhaseReordersMonths(): void
    {
        $mockRepo = self::createStub(LeagueScheduleRepositoryInterface::class);
        $mockRepo->method('getAllGamesWithBoxScoreInfo')->willReturn([
            [
                'id' => 1,
                'game_date' => '2025-11-01',
                'visitor_teamid' => 1,
                'visitor_score' => 100,
                'home_teamid' => 2,
                'home_score' => 95,
                'box_id' => 101,
                'game_of_that_day' => 1,
            ],
            [
                'id' => 2,
                'game_date' => '2026-06-01',
                'visitor_teamid' => 3,
                'visitor_score' => 110,
                'home_teamid' => 4,
                'home_score' => 105,
                'box_id' => 102,
                'game_of_that_day' => 1,
            ],
        ]);
        $mockRepo->method('getTeamRecords')->willReturn([]);

        $season = self::createStub(Season::class);
        $season->projectedNextSimEndDate = new \DateTime('2025-10-15');
        $season->phase = 'Playoffs';

        $league = self::createStub(League::class);
        $league->method('getSimLengthInDays')->willReturn(7);

        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTeamnameFromTeamID')->willReturn('Team');

        $service = new LeagueScheduleService($mockRepo);
        $result = $service->getSchedulePageData($season, $league, $commonRepo);

        $this->assertTrue($result['isPlayoffPhase']);
        $this->assertSame('2026-06', $result['playoffMonthKey']);

        // Playoffs month should be first
        $monthKeys = array_keys($result['gamesByMonth']);
        $this->assertSame('2026-06', $monthKeys[0]);
        $this->assertSame('Playoffs', $result['gamesByMonth']['2026-06']['label']);
    }

    public function testVisitorWonAndHomeWonFlags(): void
    {
        $mockRepo = self::createStub(LeagueScheduleRepositoryInterface::class);
        $mockRepo->method('getAllGamesWithBoxScoreInfo')->willReturn([
            [
                'id' => 1,
                'game_date' => '2025-11-01',
                'visitor_teamid' => 1,
                'visitor_score' => 100,
                'home_teamid' => 2,
                'home_score' => 95,
                'box_id' => 101,
                'game_of_that_day' => 1,
            ],
        ]);
        $mockRepo->method('getTeamRecords')->willReturn([]);

        $season = self::createStub(Season::class);
        $season->projectedNextSimEndDate = new \DateTime('2025-10-15');
        $season->phase = 'Regular Season';

        $league = self::createStub(League::class);
        $league->method('getSimLengthInDays')->willReturn(7);

        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTeamnameFromTeamID')->willReturn('Team');

        $service = new LeagueScheduleService($mockRepo);
        $result = $service->getSchedulePageData($season, $league, $commonRepo);

        $game = $result['gamesByMonth']['2025-11']['dates']['2025-11-01'][0];
        $this->assertTrue($game['visitorWon']);
        $this->assertFalse($game['homeWon']);
    }

    public function testGetSchedulePageDataPassesSeasonEndingYearToRepository(): void
    {
        $mockRepo = $this->createMock(LeagueScheduleRepositoryInterface::class);
        $mockRepo->expects($this->once())
            ->method('getAllGamesWithBoxScoreInfo')
            ->with(2026)
            ->willReturn([]);
        $mockRepo->method('getTeamRecords')->willReturn([]);

        $season = self::createStub(Season::class);
        $season->endingYear = 2026;
        $season->projectedNextSimEndDate = new \DateTime('2025-11-15');
        $season->phase = 'Regular Season';

        $league = self::createStub(League::class);
        $league->method('getSimLengthInDays')->willReturn(7);

        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);

        $service = new LeagueScheduleService($mockRepo);
        $service->getSchedulePageData($season, $league, $commonRepo);
    }

    public function testRegularSeasonIsNotPlayoffPhase(): void
    {
        $mockRepo = self::createStub(LeagueScheduleRepositoryInterface::class);
        $mockRepo->method('getAllGamesWithBoxScoreInfo')->willReturn([]);
        $mockRepo->method('getTeamRecords')->willReturn([]);

        $season = self::createStub(Season::class);
        $season->projectedNextSimEndDate = new \DateTime('2025-11-15');
        $season->phase = 'Regular Season';

        $league = self::createStub(League::class);
        $league->method('getSimLengthInDays')->willReturn(7);

        $commonRepo = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);

        $service = new LeagueScheduleService($mockRepo);
        $result = $service->getSchedulePageData($season, $league, $commonRepo);

        $this->assertFalse($result['isPlayoffPhase']);
        $this->assertNull($result['playoffMonthKey']);
    }
}
