<?php

declare(strict_types=1);

namespace Tests\LeagueSchedule;

use LeagueSchedule\Contracts\LeagueScheduleRepositoryInterface;
use LeagueSchedule\LeagueScheduleService;
use LeagueSchedule\Contracts\LeagueScheduleServiceInterface;
use League\League;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Season\Season;

/**
 * @phpstan-import-type ScheduleRow from LeagueScheduleRepositoryInterface
 * @phpstan-import-type SchedulePageData from LeagueScheduleServiceInterface
 */
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

    public function testBuildsCompleteGameRowForPlayedGame(): void
    {
        $r = $this->runPage(
            [self::game(101, '2025-11-01', 1, 100, 2, 95)],
            'Regular Season',
            '2025-10-15',
            [1 => '25-10', 2 => '20-15'],
            [1 => 80.0, 2 => 50.0]
        );

        $this->assertSame([
            'date' => '2025-11-01',
            'visitor' => 1,
            'visitorScore' => 100,
            'visitorTeam' => 'Team 1',
            'visitorRecord' => '25-10',
            'home' => 2,
            'homeScore' => 95,
            'homeTeam' => 'Team 2',
            'home_record' => '20-15',
            'boxid' => 101,
            'gameOfThatDay' => 1,
            'boxScoreUrl' => 'modules.php?name=GameBoxscore&date=2025-11-01&game=1',
            'visitorBoxScoreUrl' => 'modules.php?name=GameBoxscore&date=2025-11-01&game=1&team=1',
            'homeBoxScoreUrl' => 'modules.php?name=GameBoxscore&date=2025-11-01&game=1&team=2',
            'isUnplayed' => false,
            'isUpcoming' => false,
            'visitorWon' => true,
            'homeWon' => false,
            'visitorTier' => 'elite',
            'homeTier' => 'average',
        ], $r['gamesByMonth']['2025-11']['dates']['2025-11-01'][0]);
        $this->assertNull($r['firstUnplayedId']);

        // gameOfThatDay 0 falls back to the box-score file URL
        $legacy = $this->runPage([self::game(101, '2025-11-01', 1, 100, 2, 95, 0)], 'Regular Season');
        $this->assertSame(
            './ibl/IBL/box101.htm',
            $legacy['gamesByMonth']['2025-11']['dates']['2025-11-01'][0]['boxScoreUrl']
        );
        $this->assertSame(
            './ibl/IBL/box101.htm',
            $legacy['gamesByMonth']['2025-11']['dates']['2025-11-01'][0]['visitorBoxScoreUrl']
        );
        $this->assertSame(
            './ibl/IBL/box101.htm',
            $legacy['gamesByMonth']['2025-11']['dates']['2025-11-01'][0]['homeBoxScoreUrl']
        );
    }

    public function testUnknownTeamsFallBackToEmptyStringsAndBottomTier(): void
    {
        $commonRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $commonRepo->method('getTeamnameFromTeamID')->willReturn(null);

        $r = $this->runPage(
            [self::game(102, '2025-11-02', 3, 90, 4, 80, 0)],
            'Regular Season',
            '2025-10-15',
            [1 => '25-10'],
            [1 => 80.0],
            $commonRepo
        );

        $row = $r['gamesByMonth']['2025-11']['dates']['2025-11-02'][0];
        // An unranked team falls back to 0.0, which is the bottom tier. The
        // OneZeroFloat mutant (1.0) is equivalent here, so the fallback lives in
        // the UNRANKED_POWER_RANKING constant.
        $this->assertSame('', $row['visitorTeam']);
        $this->assertSame('', $row['homeTeam']);
        $this->assertSame('', $row['visitorRecord']);
        $this->assertSame('', $row['home_record']);
        $this->assertSame('bottom', $row['visitorTier']);
        $this->assertSame('bottom', $row['homeTier']);
    }

    public function testHomeWinAndTieFlags(): void
    {
        $r = $this->runPage(
            [
                self::game(103, '2025-11-03', 1, 90, 2, 100),
                self::game(104, '2025-11-03', 3, 0, 4, 0),
            ],
            'Regular Season',
            '2025-10-15'
        );

        $rows = $r['gamesByMonth']['2025-11']['dates']['2025-11-03'];
        $this->assertFalse($rows[0]['visitorWon']);
        $this->assertTrue($rows[0]['homeWon']);
        $this->assertFalse($rows[1]['visitorWon']);
        $this->assertFalse($rows[1]['homeWon']);
        $this->assertTrue($rows[1]['isUnplayed']);
        $this->assertFalse($rows[1]['isUpcoming']);
    }

    public function testFirstUnplayedIdIsFirstUpcomingGameInRawOrder(): void
    {
        $r = $this->runPage(
            [
                self::game(201, '2025-11-01', 1, 100, 2, 95),
                self::game(202, '2025-11-05', 3, 0, 4, 0),
                self::game(203, '2025-11-06', 5, 0, 6, 0),
            ],
            'Regular Season',
            '2025-11-20'
        );

        // Dropping the null guard yields game-203; an OR on isUpcoming yields game-201.
        $this->assertNotSame('game-203', $r['firstUnplayedId']);
        $this->assertNotSame('game-201', $r['firstUnplayedId']);
        $this->assertSame('game-202', $r['firstUnplayedId']);
        $this->assertFalse($r['gamesByMonth']['2025-11']['dates']['2025-11-01'][0]['isUpcoming']);
        $this->assertTrue($r['gamesByMonth']['2025-11']['dates']['2025-11-06'][0]['isUpcoming']);
    }

    public function testUnplayedGameBeyondSimWindowIsNotUpcoming(): void
    {
        $r = $this->runPage(
            [self::game(301, '2025-11-10', 1, 0, 2, 0)],
            'Regular Season',
            '2025-11-01'
        );

        $row = $r['gamesByMonth']['2025-11']['dates']['2025-11-10'][0];
        $this->assertTrue($row['isUnplayed']);
        $this->assertFalse($row['isUpcoming']);
        $this->assertNotSame('game-301', $r['firstUnplayedId']);
        $this->assertNull($r['firstUnplayedId']);
    }

    public function testInvalidDateFallsBackToUnknownMonth(): void
    {
        $r = $this->runPage(
            [self::game(401, 'not-a-date', 1, 0, 2, 0)],
            'Regular Season',
            '2025-11-15'
        );

        $m = $r['gamesByMonth'];
        $this->assertSame(['1970-01'], array_keys($m));
        $this->assertSame('Unknown', $m['1970-01']['label']);
        $this->assertSame(['not-a-date'], array_keys($m['1970-01']['dates']));
        $row = $m['1970-01']['dates']['not-a-date'][0];
        $this->assertFalse($row['isUpcoming']);
        $this->assertTrue($row['isUnplayed']);
        $this->assertNull($r['firstUnplayedId']);
    }

    public function testAccumulatesGamesWithinSameMonthAndSameDate(): void
    {
        $r = $this->runPage(
            [
                self::game(501, '2025-11-01', 1, 100, 2, 90),
                self::game(502, '2025-11-01', 3, 100, 4, 90),
                self::game(503, '2025-11-05', 5, 100, 6, 90),
            ],
            'Regular Season'
        );

        $m = $r['gamesByMonth'];
        $this->assertSame(['2025-11'], array_keys($m));
        $this->assertSame('November', $m['2025-11']['label']);
        $this->assertSame(['2025-11-01', '2025-11-05'], array_keys($m['2025-11']['dates']));
        // Resetting the date list on each game would leave [502].
        $this->assertNotSame([502], array_column($m['2025-11']['dates']['2025-11-01'], 'boxid'));
        $this->assertSame([501, 502], array_column($m['2025-11']['dates']['2025-11-01'], 'boxid'));
        $this->assertSame([503], array_column($m['2025-11']['dates']['2025-11-05'], 'boxid'));
    }

    #[DataProvider('offseasonPlayoffPhaseProvider')]
    public function testOffseasonPlayoffPhasesMoveJuneToFront(string $phase): void
    {
        $r = $this->runPage(
            [
                self::game(701, '2025-11-01', 1, 100, 2, 95),
                self::game(702, '2026-06-01', 3, 110, 4, 105),
            ],
            $phase
        );

        $m = $r['gamesByMonth'];
        $this->assertTrue($r['isPlayoffPhase']);
        $this->assertSame('2026-06', $r['playoffMonthKey']);
        $this->assertSame(['2026-06', '2025-11'], array_keys($m));
        $this->assertSame('Playoffs', $m['2026-06']['label']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function offseasonPlayoffPhaseProvider(): array
    {
        return [
            'Draft' => ['Draft'],
            'Free Agency' => ['Free Agency'],
        ];
    }

    public function testPlayoffPhaseWithoutJuneGamesKeepsMonthOrder(): void
    {
        $r = $this->runPage(
            [
                self::game(801, '2025-11-01', 1, 100, 2, 95),
                self::game(802, '2025-12-01', 3, 110, 4, 105),
            ],
            'Playoffs'
        );

        $m = $r['gamesByMonth'];
        $this->assertTrue($r['isPlayoffPhase']);
        $this->assertNull($r['playoffMonthKey']);
        $this->assertSame(['2025-11', '2025-12'], array_keys($m));
        $this->assertSame('November', $m['2025-11']['label']);
        $this->assertSame('December', $m['2025-12']['label']);
    }

    public function testRegularSeasonLeavesJuneUntouched(): void
    {
        $r = $this->runPage(
            [
                self::game(901, '2025-11-01', 1, 100, 2, 95),
                self::game(902, '2026-06-01', 3, 110, 4, 105),
            ],
            'Regular Season'
        );

        $m = $r['gamesByMonth'];
        $this->assertFalse($r['isPlayoffPhase']);
        $this->assertNull($r['playoffMonthKey']);
        $this->assertSame(['2025-11', '2026-06'], array_keys($m));
        $this->assertSame('June', $m['2026-06']['label']);
    }

    public function testPlayoffPhasePicksFirstJuneMonthAndKeepsRemainingOrder(): void
    {
        $r = $this->runPage(
            [
                self::game(1001, '2025-11-01', 1, 100, 2, 95),
                self::game(1002, '2025-06-10', 3, 100, 4, 95),
                self::game(1003, '2025-12-01', 5, 100, 6, 95),
                self::game(1004, '2026-06-01', 7, 100, 8, 95),
            ],
            'Playoffs'
        );

        $m = $r['gamesByMonth'];
        $this->assertSame('2025-06', $r['playoffMonthKey']);
        $this->assertSame(['2025-06', '2025-11', '2025-12', '2026-06'], array_keys($m));
        $this->assertSame('Playoffs', $m['2025-06']['label']);
        $this->assertSame('June', $m['2026-06']['label']);
    }

    public function testResolvesTeamNamesVisitorThenHomePerGame(): void
    {
        $calls = [];
        $commonRepo = self::createMock(TeamIdentityRepositoryInterface::class);
        $commonRepo->expects($this->exactly(4))
            ->method('getTeamnameFromTeamID')
            ->willReturnCallback(static function (int $id) use (&$calls): string {
                $calls[] = $id;
                return 'Team ' . $id;
            });

        $r = $this->runPage(
            [
                self::game(1101, '2025-11-01', 7, 100, 8, 90),
                self::game(1102, '2025-11-02', 9, 100, 10, 90),
            ],
            'Regular Season',
            '2025-10-15',
            [],
            [],
            $commonRepo
        );

        $this->assertSame([7, 8, 9, 10], $calls);
        $row = $r['gamesByMonth']['2025-11']['dates']['2025-11-01'][0];
        $this->assertSame('Team 7', $row['visitorTeam']);
        $this->assertSame('Team 8', $row['homeTeam']);
    }

    /**
     * @return ScheduleRow
     */
    private static function game(
        int $boxId,
        string $date,
        int $visitor,
        int $visitorScore,
        int $home,
        int $homeScore,
        int $gameOfThatDay = 1
    ): array {
        return [
            'id' => $boxId,
            'game_date' => $date,
            'visitor_teamid' => $visitor,
            'visitor_score' => $visitorScore,
            'home_teamid' => $home,
            'home_score' => $homeScore,
            'box_id' => $boxId,
            'game_of_that_day' => $gameOfThatDay,
        ];
    }

    /**
     * @param list<ScheduleRow> $rows
     * @param array<int, string> $records
     * @param array<int, float> $rankings
     * @return SchedulePageData
     */
    private function runPage(
        array $rows,
        string $phase,
        string $simEnd = '2025-10-15',
        array $records = [],
        array $rankings = [],
        ?TeamIdentityRepositoryInterface $commonRepo = null
    ): array {
        $repo = self::createStub(LeagueScheduleRepositoryInterface::class);
        $repo->method('getAllGamesWithBoxScoreInfo')->willReturn($rows);
        $repo->method('getTeamRecords')->willReturn($records);

        $season = self::createStub(Season::class);
        $season->projectedNextSimEndDate = new \DateTime($simEnd);
        $season->phase = $phase;

        $league = self::createStub(League::class);
        $league->method('getSimLengthInDays')->willReturn(7);

        if ($commonRepo === null) {
            $commonRepo = self::createStub(TeamIdentityRepositoryInterface::class);
            $commonRepo->method('getTeamnameFromTeamID')
                ->willReturnCallback(static fn (int $id): string => 'Team ' . $id);
        }

        return (new LeagueScheduleService($repo, $rankings))
            ->getSchedulePageData($season, $league, $commonRepo);
    }
}
