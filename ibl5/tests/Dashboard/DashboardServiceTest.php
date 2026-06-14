<?php

declare(strict_types=1);

namespace Tests\Dashboard;

use CapSpace\CapSpaceService;
use Dashboard\DashboardService;
use FreeAgencyPreview\Contracts\FreeAgencyPreviewServiceInterface;
use Injuries\Contracts\InjuriesServiceInterface;
use LeagueSchedule\Game;
use NextSim\Contracts\NextSimServiceInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Season\Season;
use Team\Team;
use Topics\Contracts\TopicsServiceInterface;
use Trading\Contracts\TradingServiceInterface;

/**
 * DashboardServiceTest - Unit tests for DashboardService aggregator.
 *
 * @covers \Dashboard\DashboardService
 */
#[AllowMockObjectsWithoutExpectations]
final class DashboardServiceTest extends TestCase
{
    /** @var TradingServiceInterface&\PHPUnit\Framework\MockObject\MockObject */
    private TradingServiceInterface $tradingService;

    /** @var NextSimServiceInterface&\PHPUnit\Framework\MockObject\MockObject */
    private NextSimServiceInterface $nextSimService;

    /** @var CapSpaceService&\PHPUnit\Framework\MockObject\MockObject */
    private CapSpaceService $capSpaceService;

    /** @var FreeAgencyPreviewServiceInterface&\PHPUnit\Framework\MockObject\MockObject */
    private FreeAgencyPreviewServiceInterface $freeAgencyPreviewService;

    /** @var InjuriesServiceInterface&\PHPUnit\Framework\MockObject\MockObject */
    private InjuriesServiceInterface $injuriesService;

    /** @var TopicsServiceInterface&\PHPUnit\Framework\MockObject\MockObject */
    private TopicsServiceInterface $topicsService;

    private DashboardService $service;

    /** @var Season&\PHPUnit\Framework\MockObject\MockObject */
    private Season $season;

    protected function setUp(): void
    {
        $this->tradingService = $this->createMock(TradingServiceInterface::class);
        $this->nextSimService = $this->createMock(NextSimServiceInterface::class);
        $this->capSpaceService = $this->createMock(CapSpaceService::class);
        $this->freeAgencyPreviewService = $this->createMock(FreeAgencyPreviewServiceInterface::class);
        $this->injuriesService = $this->createMock(InjuriesServiceInterface::class);
        $this->topicsService = $this->createMock(TopicsServiceInterface::class);

        $this->service = new DashboardService(
            $this->tradingService,
            $this->nextSimService,
            $this->capSpaceService,
            $this->freeAgencyPreviewService,
            $this->injuriesService,
            $this->topicsService,
        );

        $this->season = $this->createMock(Season::class);
        $this->season->endingYear = 2026;
    }

    // -----------------------------------------------------------------------
    // Helpers — stub the collaborators that are not under test in each case
    // -----------------------------------------------------------------------

    private function stubEmptyTrades(): void
    {
        $this->tradingService->method('getTradeReviewPageData')->willReturn([
            'userTeam'    => 'Metros',
            'userTeamId'  => 1,
            'tradeOffers' => [],
            'teams'       => [],
        ]);
    }

    private function stubNoNextSim(): void
    {
        $this->nextSimService->method('getNextSimGames')->willReturn([]);
    }

    private function stubCapRow(int $teamId, int $headroom): void
    {
        $this->capSpaceService->method('getTeamsCapData')->willReturn([
            [
                'team'            => $this->createMock(Team::class),
                'teamId'          => $teamId,
                'teamName'        => 'Metros',
                'teamCity'        => 'Metro City',
                'color1'          => '000000',
                'color2'          => 'FFFFFF',
                'availableSalary' => ['year1' => $headroom, 'year2' => 0, 'year3' => 0, 'year4' => 0, 'year5' => 0, 'year6' => 0],
                'positionSalaries' => [],
                'freeAgencySlots'  => 10,
                'has_mle'          => false,
                'has_lle'          => false,
            ],
        ]);
    }

    private function stubNoFreeAgents(): void
    {
        $this->freeAgencyPreviewService->method('getUpcomingFreeAgents')->willReturn([]);
    }

    private function stubNoInjuries(): void
    {
        $this->injuriesService->method('getInjuredPlayersWithTeams')->willReturn([]);
    }

    private function stubEmptyNews(): void
    {
        $this->topicsService->method('getPageData')->willReturn([
            'topics'        => [],
            'searchFilters' => ['topics' => [], 'categories' => [], 'authors' => [], 'articleComm' => false],
        ]);
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    public function testPendingTradesReturnsOwnerOffers(): void
    {
        $this->tradingService->method('getTradeReviewPageData')->willReturn([
            'userTeam'    => 'Metros',
            'userTeamId'  => 1,
            'tradeOffers' => [
                10 => ['oppositeTeam' => 'Stars', 'approval' => 'Pending', 'hasHammer' => true,  'from' => 'Metros', 'to' => 'Stars', 'items' => [], 'previewData' => []],
                11 => ['oppositeTeam' => 'Hawks', 'approval' => 'Rejected', 'hasHammer' => false, 'from' => 'Hawks', 'to' => 'Metros', 'items' => [], 'previewData' => []],
            ],
            'teams' => [],
        ]);
        $this->stubNoNextSim();
        $this->stubCapRow(1, 5000);
        $this->stubNoFreeAgents();
        $this->stubNoInjuries();
        $this->stubEmptyNews();

        $result = $this->service->getDashboardData(1, 'Metros', 'metrosgm', $this->season);

        $this->assertSame(2, $result['pendingTrades']['count']);
        $this->assertCount(2, $result['pendingTrades']['offers']);
        $this->assertSame('Stars', $result['pendingTrades']['offers'][0]['oppositeTeam']);
        $this->assertSame('Hawks', $result['pendingTrades']['offers'][1]['oppositeTeam']);
        $this->assertTrue($result['pendingTrades']['offers'][0]['hasHammer']);
    }

    public function testNextSimReturnsOwnerOpponent(): void
    {
        $this->stubEmptyTrades();
        $this->stubCapRow(1, 5000);
        $this->stubNoFreeAgents();
        $this->stubNoInjuries();
        $this->stubEmptyNews();

        $team = $this->createMock(Team::class);
        $team->name = 'Stars';

        $game = $this->createMock(Game::class);

        $this->nextSimService->method('getNextSimGames')->willReturn([
            42 => [
                'game'               => $game,
                'date'               => new \DateTime('2026-01-15'),
                'dayNumber'          => 42,
                'opposingTeam'       => $team,
                'locationPrefix'     => 'vs',
                'opposingStarters'   => [],
                'opponentTier'       => 'Elite',
                'opponentPowerRanking' => 1.5,
            ],
        ]);

        $result = $this->service->getDashboardData(1, 'Metros', 'metrosgm', $this->season);

        $this->assertNotNull($result['nextSim']);
        $this->assertSame('Stars', $result['nextSim']['opponent']);
        $this->assertSame('vs', $result['nextSim']['location']);
        $this->assertSame('Elite', $result['nextSim']['tier']);
        $this->assertSame('2026-01-15', $result['nextSim']['date']);
    }

    public function testNextSimReturnsNullWhenNoGames(): void
    {
        $this->stubEmptyTrades();
        $this->stubNoNextSim();
        $this->stubCapRow(1, 5000);
        $this->stubNoFreeAgents();
        $this->stubNoInjuries();
        $this->stubEmptyNews();

        $result = $this->service->getDashboardData(1, 'Metros', 'metrosgm', $this->season);

        $this->assertNull($result['nextSim']);
    }

    public function testCapHeadroomMatchesCapSpaceService(): void
    {
        $this->stubEmptyTrades();
        $this->stubNoNextSim();
        $this->stubNoFreeAgents();
        $this->stubNoInjuries();
        $this->stubEmptyNews();

        $this->capSpaceService->method('getTeamsCapData')->willReturn([
            [
                'team'             => $this->createMock(Team::class),
                'teamId'           => 1,
                'teamName'         => 'Metros',
                'teamCity'         => 'Metro City',
                'color1'           => '000000',
                'color2'           => 'FFFFFF',
                'availableSalary'  => ['year1' => 3500, 'year2' => 0, 'year3' => 0, 'year4' => 0, 'year5' => 0, 'year6' => 0],
                'positionSalaries' => [],
                'freeAgencySlots'  => 10,
                'has_mle'          => false,
                'has_lle'          => false,
            ],
            [
                'team'             => $this->createMock(Team::class),
                'teamId'           => 2,
                'teamName'         => 'Hawks',
                'teamCity'         => 'Hawk City',
                'color1'           => 'FF0000',
                'color2'           => '0000FF',
                'availableSalary'  => ['year1' => 9999, 'year2' => 0, 'year3' => 0, 'year4' => 0, 'year5' => 0, 'year6' => 0],
                'positionSalaries' => [],
                'freeAgencySlots'  => 5,
                'has_mle'          => true,
                'has_lle'          => false,
            ],
        ]);

        $result = $this->service->getDashboardData(1, 'Metros', 'metrosgm', $this->season);

        $this->assertSame(3500, $result['cap']['headroom']);
    }

    public function testUpcomingFreeAgentsFilteredToOwner(): void
    {
        $this->stubEmptyTrades();
        $this->stubNoNextSim();
        $this->stubCapRow(1, 5000);
        $this->stubNoInjuries();
        $this->stubEmptyNews();

        $this->freeAgencyPreviewService->method('getUpcomingFreeAgents')->willReturn([
            $this->makeFreeAgent(101, 1, 'Alice', 'PG'),
            $this->makeFreeAgent(102, 0, 'Bob',   'SG'),   // Free agent (teamid=0) — excluded
            $this->makeFreeAgent(103, 2, 'Carol', 'SF'),   // Other team — excluded
        ]);

        $result = $this->service->getDashboardData(1, 'Metros', 'metrosgm', $this->season);

        $this->assertCount(1, $result['upcomingFreeAgents']);
        $this->assertSame(101, $result['upcomingFreeAgents'][0]['pid']);
        $this->assertSame('Alice', $result['upcomingFreeAgents'][0]['name']);

        // Negative: teamid=2 must not appear
        $pids = array_column($result['upcomingFreeAgents'], 'pid');
        $this->assertNotContains(103, $pids);
    }

    public function testInjuriesFilteredToOwner(): void
    {
        $this->stubEmptyTrades();
        $this->stubNoNextSim();
        $this->stubCapRow(1, 5000);
        $this->stubNoFreeAgents();
        $this->stubEmptyNews();

        $this->injuriesService->method('getInjuredPlayersWithTeams')->willReturn([
            1 => $this->makeInjury(201, 'Dave',  'PG', 5,  1),
            2 => $this->makeInjury(202, 'Eve',   'SG', 10, 2),   // Other team — excluded
            3 => $this->makeInjury(203, 'Frank', 'SF', 3,  14),  // Other team — excluded
        ]);

        $result = $this->service->getDashboardData(1, 'Metros', 'metrosgm', $this->season);

        $this->assertCount(1, $result['injuries']);
        $this->assertSame(201, $result['injuries'][0]['playerID']);

        // Negative: teamid=2 and teamid=14 must not appear
        $ids = array_column($result['injuries'], 'playerID');
        $this->assertNotContains(202, $ids);
        $this->assertNotContains(203, $ids);
    }

    public function testInjuriesEmptyWhenNoOwnerPlayers(): void
    {
        $this->stubEmptyTrades();
        $this->stubNoNextSim();
        $this->stubCapRow(1, 5000);
        $this->stubNoFreeAgents();
        $this->stubEmptyNews();

        $this->injuriesService->method('getInjuredPlayersWithTeams')->willReturn([
            1 => $this->makeInjury(301, 'Grace', 'C', 7, 2),
            2 => $this->makeInjury(302, 'Heidi', 'PF', 2, 3),
        ]);

        $result = $this->service->getDashboardData(1, 'Metros', 'metrosgm', $this->season);

        $this->assertSame([], $result['injuries']);
    }

    public function testNewsIsLeagueWideNotFiltered(): void
    {
        $this->stubEmptyTrades();
        $this->stubNoNextSim();
        $this->stubCapRow(1, 5000);
        $this->stubNoFreeAgents();
        $this->stubNoInjuries();

        // Articles from two different topics — teamids not involved; league-wide
        $this->topicsService->method('getPageData')->willReturn([
            'topics' => [
                1 => [
                    'topicId'        => 1,
                    'topicName'      => 'Trades',
                    'topicImage'     => '',
                    'topicText'      => '',
                    'storyCount'     => 3,
                    'totalReads'     => 0,
                    'recentArticles' => [
                        ['sid' => 10, 'title' => 'Big Trade Incoming', 'catId' => 1, 'catTitle' => 'Trades'],
                        ['sid' => 8,  'title' => 'Counter Offer Made', 'catId' => 1, 'catTitle' => 'Trades'],
                    ],
                ],
                2 => [
                    'topicId'        => 2,
                    'topicName'      => 'Injuries',
                    'topicImage'     => '',
                    'topicText'      => '',
                    'storyCount'     => 2,
                    'totalReads'     => 0,
                    'recentArticles' => [
                        ['sid' => 9, 'title' => 'Star Player Out', 'catId' => 2, 'catTitle' => 'Injuries'],
                    ],
                ],
            ],
            'searchFilters' => ['topics' => [], 'categories' => [], 'authors' => [], 'articleComm' => false],
        ]);

        $result = $this->service->getDashboardData(1, 'Metros', 'metrosgm', $this->season);

        // Should contain articles from both topics (league-wide), sorted by sid DESC, max 5
        $this->assertGreaterThan(0, count($result['news']));
        $titles = array_column($result['news'], 'title');
        $this->assertContains('Big Trade Incoming', $titles);
        $this->assertContains('Star Player Out', $titles);
        $this->assertContains('Counter Offer Made', $titles);

        // Sorted by sid DESC: 10, 9, 8
        $this->assertSame(10, $result['news'][0]['sid']);
        $this->assertSame(9,  $result['news'][1]['sid']);
        $this->assertSame(8,  $result['news'][2]['sid']);
    }

    // -----------------------------------------------------------------------
    // Private factory helpers
    // -----------------------------------------------------------------------

    /**
     * @return array{pid: int, teamid: int, name: string, teamname: string, team_city: string, color1: string, color2: string, pos: string, age: int, r_fga: int, r_fgp: int, r_fta: int, r_ftp: int, r_3ga: int, r_3gp: int, r_orb: int, r_drb: int, r_ast: int, r_stl: int, r_blk: int, r_tvr: int, r_foul: int, oo: int, r_drive_off: int, po: int, r_trans_off: int, od: int, dd: int, pd: int, td: int, loyalty: int, winner: int, playing_time: int, security: int, tradition: int}
     */
    private function makeFreeAgent(int $pid, int $teamid, string $name, string $pos): array
    {
        return [
            'pid' => $pid, 'teamid' => $teamid, 'name' => $name, 'teamname' => 'Team',
            'team_city' => 'City', 'color1' => '000000', 'color2' => 'FFFFFF',
            'pos' => $pos, 'age' => 25, 'r_fga' => 0, 'r_fgp' => 0, 'r_fta' => 0,
            'r_ftp' => 0, 'r_3ga' => 0, 'r_3gp' => 0, 'r_orb' => 0, 'r_drb' => 0,
            'r_ast' => 0, 'r_stl' => 0, 'r_blk' => 0, 'r_tvr' => 0, 'r_foul' => 0,
            'oo' => 0, 'r_drive_off' => 0, 'po' => 0, 'r_trans_off' => 0,
            'od' => 0, 'dd' => 0, 'pd' => 0, 'td' => 0, 'loyalty' => 0,
            'winner' => 0, 'playing_time' => 0, 'security' => 0, 'tradition' => 0,
        ];
    }

    /**
     * @return array{playerID: int, name: string, position: string, daysRemaining: int, returnDate: string, teamid: int, teamCity: string, teamName: string, teamColor1: string, teamColor2: string}
     */
    private function makeInjury(int $playerID, string $name, string $position, int $daysRemaining, int $teamid): array
    {
        return [
            'playerID'      => $playerID,
            'name'          => $name,
            'position'      => $position,
            'daysRemaining' => $daysRemaining,
            'returnDate'    => '2026-02-01',
            'teamid'        => $teamid,
            'teamCity'      => 'City',
            'teamName'      => 'Team',
            'teamColor1'    => '000000',
            'teamColor2'    => 'FFFFFF',
        ];
    }
}
