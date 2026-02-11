<?php

declare(strict_types=1);

namespace Tests\LeagueSchedule;

use LeagueSchedule\Contracts\LeagueScheduleRepositoryInterface;
use LeagueSchedule\LeagueScheduleService;
use LeagueSchedule\Contracts\LeagueScheduleServiceInterface;
use PHPUnit\Framework\TestCase;

class LeagueScheduleServiceTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $mockRepo = $this->createStub(LeagueScheduleRepositoryInterface::class);
        $service = new LeagueScheduleService($mockRepo);

        $this->assertInstanceOf(LeagueScheduleServiceInterface::class, $service);
    }

    public function testGetSchedulePageDataReturnsExpectedStructure(): void
    {
        $mockRepo = $this->createStub(LeagueScheduleRepositoryInterface::class);
        $mockRepo->method('getAllGamesWithBoxScoreInfo')->willReturn([]);
        $mockRepo->method('getTeamRecords')->willReturn([]);

        $season = $this->createStub(\Season::class);
        $season->projectedNextSimEndDate = new \DateTime('2025-11-15');
        $season->phase = 'Regular Season';

        $league = $this->createStub(\League::class);
        $league->method('getSimLengthInDays')->willReturn(7);

        $commonRepo = $this->createStub(\Services\CommonMysqliRepository::class);

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
        $mockRepo = $this->createStub(LeagueScheduleRepositoryInterface::class);
        $mockRepo->method('getAllGamesWithBoxScoreInfo')->willReturn([
            [
                'SchedID' => 1,
                'Date' => '2025-11-01',
                'Visitor' => 1,
                'VScore' => 100,
                'Home' => 2,
                'HScore' => 95,
                'BoxID' => 101,
                'gameOfThatDay' => 1,
            ],
            [
                'SchedID' => 2,
                'Date' => '2025-12-01',
                'Visitor' => 3,
                'VScore' => 110,
                'Home' => 4,
                'HScore' => 105,
                'BoxID' => 102,
                'gameOfThatDay' => 1,
            ],
        ]);
        $mockRepo->method('getTeamRecords')->willReturn([
            1 => '25-10',
            2 => '20-15',
            3 => '22-13',
            4 => '18-17',
        ]);

        $season = $this->createStub(\Season::class);
        $season->projectedNextSimEndDate = new \DateTime('2025-10-15');
        $season->phase = 'Regular Season';

        $league = $this->createStub(\League::class);
        $league->method('getSimLengthInDays')->willReturn(7);

        $commonRepo = $this->createStub(\Services\CommonMysqliRepository::class);
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
        $mockRepo = $this->createStub(LeagueScheduleRepositoryInterface::class);
        $mockRepo->method('getAllGamesWithBoxScoreInfo')->willReturn([
            [
                'SchedID' => 1,
                'Date' => '2025-11-10',
                'Visitor' => 1,
                'VScore' => 0,
                'Home' => 2,
                'HScore' => 0,
                'BoxID' => 101,
                'gameOfThatDay' => 0,
            ],
        ]);
        $mockRepo->method('getTeamRecords')->willReturn([]);

        $season = $this->createStub(\Season::class);
        $season->projectedNextSimEndDate = new \DateTime('2025-11-15');
        $season->phase = 'Regular Season';

        $league = $this->createStub(\League::class);
        $league->method('getSimLengthInDays')->willReturn(7);

        $commonRepo = $this->createStub(\Services\CommonMysqliRepository::class);
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
        $mockRepo = $this->createStub(LeagueScheduleRepositoryInterface::class);
        $mockRepo->method('getAllGamesWithBoxScoreInfo')->willReturn([
            [
                'SchedID' => 1,
                'Date' => '2025-11-01',
                'Visitor' => 1,
                'VScore' => 100,
                'Home' => 2,
                'HScore' => 95,
                'BoxID' => 101,
                'gameOfThatDay' => 1,
            ],
            [
                'SchedID' => 2,
                'Date' => '2026-06-01',
                'Visitor' => 3,
                'VScore' => 110,
                'Home' => 4,
                'HScore' => 105,
                'BoxID' => 102,
                'gameOfThatDay' => 1,
            ],
        ]);
        $mockRepo->method('getTeamRecords')->willReturn([]);

        $season = $this->createStub(\Season::class);
        $season->projectedNextSimEndDate = new \DateTime('2025-10-15');
        $season->phase = 'Playoffs';

        $league = $this->createStub(\League::class);
        $league->method('getSimLengthInDays')->willReturn(7);

        $commonRepo = $this->createStub(\Services\CommonMysqliRepository::class);
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
        $mockRepo = $this->createStub(LeagueScheduleRepositoryInterface::class);
        $mockRepo->method('getAllGamesWithBoxScoreInfo')->willReturn([
            [
                'SchedID' => 1,
                'Date' => '2025-11-01',
                'Visitor' => 1,
                'VScore' => 100,
                'Home' => 2,
                'HScore' => 95,
                'BoxID' => 101,
                'gameOfThatDay' => 1,
            ],
        ]);
        $mockRepo->method('getTeamRecords')->willReturn([]);

        $season = $this->createStub(\Season::class);
        $season->projectedNextSimEndDate = new \DateTime('2025-10-15');
        $season->phase = 'Regular Season';

        $league = $this->createStub(\League::class);
        $league->method('getSimLengthInDays')->willReturn(7);

        $commonRepo = $this->createStub(\Services\CommonMysqliRepository::class);
        $commonRepo->method('getTeamnameFromTeamID')->willReturn('Team');

        $service = new LeagueScheduleService($mockRepo);
        $result = $service->getSchedulePageData($season, $league, $commonRepo);

        $game = $result['gamesByMonth']['2025-11']['dates']['2025-11-01'][0];
        $this->assertTrue($game['visitorWon']);
        $this->assertFalse($game['homeWon']);
    }

    public function testRegularSeasonIsNotPlayoffPhase(): void
    {
        $mockRepo = $this->createStub(LeagueScheduleRepositoryInterface::class);
        $mockRepo->method('getAllGamesWithBoxScoreInfo')->willReturn([]);
        $mockRepo->method('getTeamRecords')->willReturn([]);

        $season = $this->createStub(\Season::class);
        $season->projectedNextSimEndDate = new \DateTime('2025-11-15');
        $season->phase = 'Regular Season';

        $league = $this->createStub(\League::class);
        $league->method('getSimLengthInDays')->willReturn(7);

        $commonRepo = $this->createStub(\Services\CommonMysqliRepository::class);

        $service = new LeagueScheduleService($mockRepo);
        $result = $service->getSchedulePageData($season, $league, $commonRepo);

        $this->assertFalse($result['isPlayoffPhase']);
        $this->assertNull($result['playoffMonthKey']);
    }
}
