<?php

declare(strict_types=1);

namespace Tests\LeagueControlPanel;

use LeagueControlPanel\LeagueControlPanelAwardGenerationService;
use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Voting\Contracts\VotingResultsServiceInterface;

/**
 * @covers \LeagueControlPanel\LeagueControlPanelAwardGenerationService
 */
class LeagueControlPanelAwardGenerationServiceTest extends TestCase
{
    /** @var LeagueControlPanelRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private LeagueControlPanelRepositoryInterface $stubRepository;

    /** @var VotingResultsServiceInterface&\PHPUnit\Framework\MockObject\Stub */
    private VotingResultsServiceInterface $stubVotingService;

    private LeagueControlPanelAwardGenerationService $service;
    private string $tempDir;

    /** @var list<string> */
    private const EXPECTED_CALL_SEQUENCE = [
        'upsertAward|Most Valuable Player (1st)|Player1',
        'upsertAward|Most Valuable Player (2nd)|Player2',
        'upsertAward|Most Valuable Player (3rd)|Player3',
        'upsertAward|Most Valuable Player (4th)|Player4',
        'upsertAward|Most Valuable Player (5th)|Player5',
        'upsertAward|6th Man Award (1st)|Six1',
        'upsertAward|6th Man Award (2nd)|Six2',
        'upsertAward|Rookie of the Year (1st)|Rook1',
        'upsertAward|Rookie of the Year (2nd)|Rook2',
        'upsertAward|Rookie of the Year (3rd)|Rook3',
        'upsertAward|Rookie of the Year (4th)|Rook4',
        'upsertAward|Rookie of the Year (5th)|Rook5',
        'upsertGmAward|BestGM',
        'upsertAward|Defensive Player of the Year (1st)|DPOY1',
        'upsertAward|Defensive Player of the Year (2nd)|DPOY2',
        'upsertAward|Defensive Player of the Year (3rd)|DPOY3',
        'upsertAward|Defensive Player of the Year (4th)|DPOY4',
        'upsertAward|Defensive Player of the Year (5th)|DPOY5',
        'upsertAward|Scoring Leader (1st)|S1',
        'upsertAward|Scoring Leader (2nd)|S2',
        'upsertAward|Scoring Leader (3rd)|S3',
        'upsertAward|Scoring Leader (4th)|S4',
        'upsertAward|Scoring Leader (5th)|S5',
        'upsertAward|Rebounding Leader (1st)|S1',
        'upsertAward|Rebounding Leader (2nd)|S2',
        'upsertAward|Rebounding Leader (3rd)|S3',
        'upsertAward|Rebounding Leader (4th)|S4',
        'upsertAward|Rebounding Leader (5th)|S5',
        'upsertAward|Assists Leader (1st)|S1',
        'upsertAward|Assists Leader (2nd)|S2',
        'upsertAward|Assists Leader (3rd)|S3',
        'upsertAward|Assists Leader (4th)|S4',
        'upsertAward|Assists Leader (5th)|S5',
        'upsertAward|Steals Leader (1st)|S1',
        'upsertAward|Steals Leader (2nd)|S2',
        'upsertAward|Steals Leader (3rd)|S3',
        'upsertAward|Steals Leader (4th)|S4',
        'upsertAward|Steals Leader (5th)|S5',
        'upsertAward|Blocks Leader (1st)|S1',
        'upsertAward|Blocks Leader (2nd)|S2',
        'upsertAward|Blocks Leader (3rd)|S3',
        'upsertAward|Blocks Leader (4th)|S4',
        'upsertAward|Blocks Leader (5th)|S5',
        'upsertAward|All-League First Team|Player1',
        'upsertAward|All-League First Team|Player2',
        'upsertAward|All-League First Team|Player3',
        'upsertAward|All-League First Team|Player4',
        'upsertAward|All-League First Team|Player5',
        'upsertAward|All-League Second Team|Player6',
        'upsertAward|All-League Second Team|Player7',
        'upsertAward|All-League Second Team|JsbB1',
        'upsertAward|All-League Second Team|JsbB2',
        'upsertAward|All-League Second Team|JsbB3',
        'upsertAward|All-League Third Team|JsbC1',
        'upsertAward|All-League Third Team|JsbC2',
        'upsertAward|All-League Third Team|JsbC3',
        'upsertAward|All-League Third Team|JsbC4',
        'upsertAward|All-League Third Team|JsbC5',
        'upsertAward|All-Rookie Team (1st)|Rook1',
        'upsertAward|All-Rookie Team (1st)|Rook2',
        'upsertAward|All-Rookie Team (1st)|Rook3',
        'upsertAward|All-Rookie Team (1st)|Rook4',
        'upsertAward|All-Rookie Team (1st)|Rook5',
        'upsertAward|All-Rookie Team (2nd)|Rook6',
        'upsertAward|All-Rookie Team (2nd)|All Rookie1P1',
        'upsertAward|All-Rookie Team (2nd)|All Rookie1P2',
        'upsertAward|All-Rookie Team (2nd)|All Rookie1P3',
        'upsertAward|All-Rookie Team (2nd)|All Rookie1P4',
        'upsertAward|All-Rookie Team (3rd)|All Rookie2P1',
        'upsertAward|All-Rookie Team (3rd)|All Rookie2P2',
        'upsertAward|All-Rookie Team (3rd)|All Rookie2P3',
        'upsertAward|All-Rookie Team (3rd)|All Rookie2P4',
        'upsertAward|All-Rookie Team (3rd)|All Rookie2P5',
        'upsertAward|All-Defensive Team (1st)|All Defense0P1',
        'upsertAward|All-Defensive Team (1st)|All Defense0P2',
        'upsertAward|All-Defensive Team (1st)|All Defense0P3',
        'upsertAward|All-Defensive Team (1st)|All Defense0P4',
        'upsertAward|All-Defensive Team (1st)|All Defense0P5',
        'upsertAward|All-Defensive Team (2nd)|All Defense1P1',
        'upsertAward|All-Defensive Team (2nd)|All Defense1P2',
        'upsertAward|All-Defensive Team (2nd)|All Defense1P3',
        'upsertAward|All-Defensive Team (2nd)|All Defense1P4',
        'upsertAward|All-Defensive Team (2nd)|All Defense1P5',
        'upsertAward|All-Defensive Team (3rd)|All Defense2P1',
        'upsertAward|All-Defensive Team (3rd)|All Defense2P2',
        'upsertAward|All-Defensive Team (3rd)|All Defense2P3',
        'upsertAward|All-Defensive Team (3rd)|All Defense2P4',
        'upsertAward|All-Defensive Team (3rd)|All Defense2P5',
    ];

    protected function setUp(): void
    {
        $this->stubRepository = self::createStub(LeagueControlPanelRepositoryInterface::class);
        $this->stubVotingService = self::createStub(VotingResultsServiceInterface::class);
        $this->service = new LeagueControlPanelAwardGenerationService($this->stubRepository, $this->stubVotingService);
        $this->tempDir = sys_get_temp_dir() . '/award_gen_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        rmdir($this->tempDir);
    }

    public function testReturnsErrorForMissingLeadersHtm(): void
    {
        $result = $this->service->generateSeasonAwards(2025, '/nonexistent/Leaders.htm');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to parse', $result['message']);
        $this->assertSame(0, $result['inserted']);
    }

    public function testInsertsIndividualAwardsFromVotes(): void
    {
        $this->stubVotingService->method('getEndOfYearResults')->willReturn([
            [
                'title' => 'Most Valuable Player',
                'rows' => [
                    ['name' => 'LeBron James, Lakers', 'votes' => 20, 'pid' => 1],
                    ['name' => 'Stephen Curry, Clippers', 'votes' => 15, 'pid' => 2],
                    ['name' => 'Kevin Durant, Nets', 'votes' => 10, 'pid' => 3],
                    ['name' => 'Giannis, Bucks', 'votes' => 8, 'pid' => 4],
                    ['name' => 'Jokic, Nuggets', 'votes' => 5, 'pid' => 5],
                ],
            ],
            ['title' => 'Sixth Man of the Year', 'rows' => []],
            ['title' => 'Rookie of the Year', 'rows' => []],
            ['title' => 'GM of the Year', 'rows' => []],
        ]);

        $expectedAwards = [
            [2025, 'Most Valuable Player (1st)', 'LeBron James'],
            [2025, 'Most Valuable Player (2nd)', 'Stephen Curry'],
            [2025, 'Most Valuable Player (3rd)', 'Kevin Durant'],
            [2025, 'Most Valuable Player (4th)', 'Giannis'],
            [2025, 'Most Valuable Player (5th)', 'Jokic'],
        ];

        $callIndex = 0;
        $this->stubRepository->method('upsertAward')
            ->willReturnCallback(function (int $year, string $award, string $name) use (&$callIndex, $expectedAwards): int {
                if ($callIndex < count($expectedAwards)) {
                    $expected = $expectedAwards[$callIndex];
                    $this->assertSame($expected[0], $year);
                    $this->assertSame($expected[1], $award);
                    $this->assertSame($expected[2], $name);
                }
                $callIndex++;
                return 1; // inserted
            });

        $leadersPath = $this->writeMinimalLeadersHtm();
        $result = $this->service->generateSeasonAwards(2025, $leadersPath);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['inserted']);
    }

    public function testInsertsGmOfTheYear(): void
    {
        $mockRepository = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $stubVotingService = self::createStub(VotingResultsServiceInterface::class);
        $service = new LeagueControlPanelAwardGenerationService($mockRepository, $stubVotingService);

        $stubVotingService->method('getEndOfYearResults')->willReturn([
            ['title' => 'Most Valuable Player', 'rows' => []],
            ['title' => 'Sixth Man of the Year', 'rows' => []],
            ['title' => 'Rookie of the Year', 'rows' => []],
            [
                'title' => 'GM of the Year',
                'rows' => [
                    ['name' => 'BestGM', 'votes' => 20, 'pid' => 0],
                ],
            ],
        ]);

        $mockRepository->expects($this->once())
            ->method('upsertGmAward')
            ->with(2025, 'BestGM')
            ->willReturn(1);

        $mockRepository->method('upsertAward')->willReturn(1);

        $leadersPath = $this->writeMinimalLeadersHtm();
        $result = $service->generateSeasonAwards(2025, $leadersPath);

        $this->assertTrue($result['success']);
    }

    public function testSkipsBlankBallotLabels(): void
    {
        $this->stubVotingService->method('getEndOfYearResults')->willReturn([
            [
                'title' => 'Most Valuable Player',
                'rows' => [
                    ['name' => '(No Selection Recorded)', 'votes' => 5, 'pid' => 0],
                    ['name' => 'Real Player, Team', 'votes' => 3, 'pid' => 1],
                ],
            ],
            ['title' => 'Sixth Man of the Year', 'rows' => []],
            ['title' => 'Rookie of the Year', 'rows' => []],
            ['title' => 'GM of the Year', 'rows' => []],
        ]);

        $capturedNames = [];
        $this->stubRepository->method('upsertAward')
            ->willReturnCallback(function (int $year, string $award, string $name) use (&$capturedNames): int {
                if (str_starts_with($award, 'Most Valuable Player')) {
                    $capturedNames[] = $name;
                }
                return 1;
            });

        $leadersPath = $this->writeMinimalLeadersHtm();
        $this->service->generateSeasonAwards(2025, $leadersPath);

        $this->assertNotContains('(No Selection Recorded)', $capturedNames);
        $this->assertContains('Real Player', $capturedNames);
    }

    public function testAllLeagueTeamsUseVoteThenJsbFill(): void
    {
        // 7 MVP vote-getters (fills first team + 2 of second team)
        $this->stubVotingService->method('getEndOfYearResults')->willReturn([
            [
                'title' => 'Most Valuable Player',
                'rows' => [
                    ['name' => 'Player1, T1', 'votes' => 20, 'pid' => 1],
                    ['name' => 'Player2, T2', 'votes' => 18, 'pid' => 2],
                    ['name' => 'Player3, T3', 'votes' => 15, 'pid' => 3],
                    ['name' => 'Player4, T4', 'votes' => 12, 'pid' => 4],
                    ['name' => 'Player5, T5', 'votes' => 10, 'pid' => 5],
                    ['name' => 'Player6, T6', 'votes' => 8, 'pid' => 6],
                    ['name' => 'Player7, T7', 'votes' => 5, 'pid' => 7],
                ],
            ],
            ['title' => 'Sixth Man of the Year', 'rows' => []],
            ['title' => 'Rookie of the Year', 'rows' => []],
            ['title' => 'GM of the Year', 'rows' => []],
        ]);

        /** @var array<string, list<string>> $awardsByTeam */
        $awardsByTeam = [];
        $this->stubRepository->method('upsertAward')
            ->willReturnCallback(function (int $year, string $award, string $name) use (&$awardsByTeam): int {
                if (str_starts_with($award, 'All-League')) {
                    $awardsByTeam[$award][] = $name;
                }
                return 1;
            });

        $leadersPath = $this->writeLeadersHtmWithTeams();
        $this->service->generateSeasonAwards(2025, $leadersPath);

        // First team should have players 1-5 from votes
        $this->assertSame(['Player1', 'Player2', 'Player3', 'Player4', 'Player5'], $awardsByTeam['All-League First Team'] ?? []);

        // Second team: players 6-7 from votes + 3 from JSB fill
        $secondTeam = $awardsByTeam['All-League Second Team'] ?? [];
        $this->assertSame('Player6', $secondTeam[0]);
        $this->assertSame('Player7', $secondTeam[1]);
        $this->assertCount(5, $secondTeam);
    }

    public function testDuplicatePlayersSkippedInTeamFill(): void
    {
        // Player1 appears in both votes and JSB — should not be added twice
        $this->stubVotingService->method('getEndOfYearResults')->willReturn([
            [
                'title' => 'Most Valuable Player',
                'rows' => [
                    ['name' => 'Player1, T1', 'votes' => 20, 'pid' => 1],
                ],
            ],
            ['title' => 'Sixth Man of the Year', 'rows' => []],
            ['title' => 'Rookie of the Year', 'rows' => []],
            ['title' => 'GM of the Year', 'rows' => []],
        ]);

        $allNames = [];
        $this->stubRepository->method('upsertAward')
            ->willReturnCallback(function (int $year, string $award, string $name) use (&$allNames): int {
                if (str_starts_with($award, 'All-League')) {
                    $allNames[] = $name;
                }
                return 1;
            });

        // JSB has Player1 on All-League 1st team — should be skipped in fill
        $leadersPath = $this->writeLeadersHtmWithDuplicatePlayer();
        $this->service->generateSeasonAwards(2025, $leadersPath);

        // Player1 should appear exactly once across all teams
        $player1Count = count(array_filter($allNames, static fn (string $n): bool => $n === 'Player1'));
        $this->assertSame(1, $player1Count);
    }

    public function testCountsInsertedVsSkipped(): void
    {
        $this->stubVotingService->method('getEndOfYearResults')->willReturn([
            ['title' => 'Most Valuable Player', 'rows' => []],
            ['title' => 'Sixth Man of the Year', 'rows' => []],
            ['title' => 'Rookie of the Year', 'rows' => []],
            ['title' => 'GM of the Year', 'rows' => []],
        ]);

        $callCount = 0;
        $this->stubRepository->method('upsertAward')
            ->willReturnCallback(function () use (&$callCount): int {
                $callCount++;
                // Alternate between inserted (1) and already exists (2)
                return ($callCount % 2 === 0) ? 2 : 1;
            });

        $leadersPath = $this->writeMinimalLeadersHtm();
        $result = $this->service->generateSeasonAwards(2025, $leadersPath);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['inserted']);
        $this->assertGreaterThan(0, $result['skipped']);
        $this->assertSame($result['inserted'] + $result['skipped'], (int) preg_replace('/.*Total: (\d+)\..*/', '$1', $result['message']));
    }

    public function testRepositoryCallSequenceIsPinned(): void
    {
        $mvpRows = [];
        for ($i = 1; $i <= 7; $i++) {
            $mvpRows[] = ['name' => 'Player' . $i . ', T' . $i, 'votes' => 30 - $i, 'pid' => $i];
        }
        $this->stubVotingService->method('getEndOfYearResults')->willReturn([
            ['title' => 'Most Valuable Player', 'rows' => $mvpRows],
            [
                'title' => 'Sixth Man of the Year',
                'rows' => [
                    ['name' => 'Six1, T', 'votes' => 5, 'pid' => 21],
                    ['name' => 'Six2, T', 'votes' => 4, 'pid' => 22],
                ],
            ],
            [
                'title' => 'Rookie of the Year',
                'rows' => [
                    ['name' => 'Rook1, T', 'votes' => 9, 'pid' => 31],
                    ['name' => 'Rook2, T', 'votes' => 8, 'pid' => 32],
                    ['name' => 'Rook3, T', 'votes' => 7, 'pid' => 33],
                    ['name' => 'Rook4, T', 'votes' => 6, 'pid' => 34],
                    ['name' => 'Rook5, T', 'votes' => 5, 'pid' => 35],
                    ['name' => 'Rook6, T', 'votes' => 4, 'pid' => 36],
                ],
            ],
            [
                'title' => 'GM of the Year',
                'rows' => [
                    ['name' => 'BestGM', 'votes' => 20, 'pid' => 0],
                ],
            ],
        ]);

        /** @var list<string> $calls */
        $calls = [];
        $this->stubRepository->method('upsertAward')
            ->willReturnCallback(function (int $year, string $award, string $name) use (&$calls): int {
                $calls[] = "upsertAward|{$award}|{$name}";
                return 1;
            });
        $this->stubRepository->method('upsertGmAward')
            ->willReturnCallback(function (int $year, string $name) use (&$calls): int {
                $calls[] = "upsertGmAward|{$name}";
                return 1;
            });

        $leadersPath = $this->writeLeadersHtmWithTeams();
        $this->service->generateSeasonAwards(2025, $leadersPath);

        $this->assertSame(self::EXPECTED_CALL_SEQUENCE, $calls);
    }

    public function testGmAwardAlreadyExistingCountsAsSkipped(): void
    {
        $this->stubVotingService->method('getEndOfYearResults')->willReturn([
            ['title' => 'Most Valuable Player', 'rows' => []],
            ['title' => 'Sixth Man of the Year', 'rows' => []],
            ['title' => 'Rookie of the Year', 'rows' => []],
            [
                'title' => 'GM of the Year',
                'rows' => [
                    ['name' => 'BestGM', 'votes' => 20, 'pid' => 0],
                ],
            ],
        ]);

        $awardCalls = 0;
        $this->stubRepository->method('upsertGmAward')->willReturn(2);
        $this->stubRepository->method('upsertAward')
            ->willReturnCallback(function () use (&$awardCalls): int {
                $awardCalls++;
                return 1;
            });

        $leadersPath = $this->writeMinimalLeadersHtm();
        $result = $this->service->generateSeasonAwards(2025, $leadersPath);

        $this->assertSame(1, $result['skipped']);
        $this->assertSame($awardCalls, $result['inserted']);
    }

    public function testMissingLeadersHtmWritesNothing(): void
    {
        $mockRepository = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mockRepository->expects($this->never())->method('upsertAward');
        $mockRepository->expects($this->never())->method('upsertGmAward');

        $stubVotingService = self::createStub(VotingResultsServiceInterface::class);
        $stubVotingService->method('getEndOfYearResults')->willReturn([
            ['title' => 'Most Valuable Player', 'rows' => []],
            ['title' => 'Sixth Man of the Year', 'rows' => []],
            ['title' => 'Rookie of the Year', 'rows' => []],
            [
                'title' => 'GM of the Year',
                'rows' => [
                    ['name' => 'BestGM', 'votes' => 20, 'pid' => 0],
                ],
            ],
        ]);
        $service = new LeagueControlPanelAwardGenerationService($mockRepository, $stubVotingService);

        $result = $service->generateSeasonAwards(2025, '/nonexistent/Leaders.htm');

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['inserted']);
        $this->assertSame(0, $result['skipped']);
    }

    public function testVoteGettersBeyondFifteenNeverReachTeamAwards(): void
    {
        $mvpRows = [];
        for ($i = 1; $i <= 17; $i++) {
            $mvpRows[] = ['name' => 'Player' . $i . ', T' . $i, 'votes' => 40 - $i, 'pid' => $i];
        }
        $this->stubVotingService->method('getEndOfYearResults')->willReturn([
            ['title' => 'Most Valuable Player', 'rows' => $mvpRows],
            ['title' => 'Sixth Man of the Year', 'rows' => []],
            ['title' => 'Rookie of the Year', 'rows' => []],
            ['title' => 'GM of the Year', 'rows' => []],
        ]);

        /** @var array<string, list<string>> $awardsByTeam */
        $awardsByTeam = [];
        $this->stubRepository->method('upsertAward')
            ->willReturnCallback(function (int $year, string $award, string $name) use (&$awardsByTeam): int {
                if (str_starts_with($award, 'All-League')) {
                    $awardsByTeam[$award][] = $name;
                }
                return 1;
            });

        $leadersPath = $this->writeLeadersHtmWithTeams();
        $this->service->generateSeasonAwards(2025, $leadersPath);

        $this->assertSame(
            ['Player11', 'Player12', 'Player13', 'Player14', 'Player15'],
            $awardsByTeam['All-League Third Team'] ?? []
        );

        $allLeague = array_merge(...array_values($awardsByTeam));
        $this->assertCount(15, $allLeague);
        $this->assertNotContains('Player16', $allLeague);
        $this->assertNotContains('Player17', $allLeague);
    }

    public function testEmptyVoteCategoriesFillTeamsFromJsbAndWriteNoRankedVoteAwards(): void
    {
        $mockRepository = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $stubVotingService = self::createStub(VotingResultsServiceInterface::class);
        $service = new LeagueControlPanelAwardGenerationService($mockRepository, $stubVotingService);

        $stubVotingService->method('getEndOfYearResults')->willReturn([
            ['title' => 'Most Valuable Player', 'rows' => []],
            ['title' => 'Sixth Man of the Year', 'rows' => []],
            ['title' => 'Rookie of the Year', 'rows' => []],
            ['title' => 'GM of the Year', 'rows' => []],
        ]);

        $mockRepository->expects($this->never())->method('upsertGmAward');

        /** @var list<array{string, string}> $captured */
        $captured = [];
        $mockRepository->method('upsertAward')
            ->willReturnCallback(function (int $year, string $award, string $name) use (&$captured): int {
                $captured[] = [$award, $name];
                return 1;
            });

        $leadersPath = $this->writeLeadersHtmWithTeams();
        $service->generateSeasonAwards(2025, $leadersPath);

        /** @var array<string, list<string>> $awardsByTeam */
        $awardsByTeam = [];
        foreach ($captured as [$award, $name]) {
            $this->assertFalse(str_starts_with($award, 'Most Valuable Player'), $award);
            $this->assertFalse(str_starts_with($award, '6th Man Award'), $award);
            $this->assertFalse(str_starts_with($award, 'Rookie of the Year'), $award);
            if (str_starts_with($award, 'All-League')) {
                $awardsByTeam[$award][] = $name;
            }
        }

        $this->assertSame(['JsbA1', 'JsbA2', 'JsbA3', 'JsbA4', 'JsbA5'], $awardsByTeam['All-League First Team'] ?? []);
        $this->assertSame(['JsbB1', 'JsbB2', 'JsbB3', 'JsbB4', 'JsbB5'], $awardsByTeam['All-League Second Team'] ?? []);
        $this->assertSame(['JsbC1', 'JsbC2', 'JsbC3', 'JsbC4', 'JsbC5'], $awardsByTeam['All-League Third Team'] ?? []);
    }

    public function testRankedAwardsStopAtAvailableNames(): void
    {
        $this->stubVotingService->method('getEndOfYearResults')->willReturn([
            ['title' => 'Most Valuable Player', 'rows' => []],
            [
                'title' => 'Sixth Man of the Year',
                'rows' => [
                    ['name' => 'Six1, T', 'votes' => 5, 'pid' => 21],
                    ['name' => 'Six2, T', 'votes' => 4, 'pid' => 22],
                ],
            ],
            ['title' => 'Rookie of the Year', 'rows' => []],
            ['title' => 'GM of the Year', 'rows' => []],
        ]);

        /** @var list<string> $captured */
        $captured = [];
        $this->stubRepository->method('upsertAward')
            ->willReturnCallback(function (int $year, string $award, string $name) use (&$captured): int {
                if (str_starts_with($award, '6th Man Award')) {
                    $captured[] = $award;
                }
                return 1;
            });

        $leadersPath = $this->writeMinimalLeadersHtm();
        $this->service->generateSeasonAwards(2025, $leadersPath);

        $this->assertSame(['6th Man Award (1st)', '6th Man Award (2nd)'], $captured);
    }

    public function testAllDefensiveTeamsAreWrittenFromLeadersHtmInOrder(): void
    {
        $this->stubVotingService->method('getEndOfYearResults')->willReturn([
            ['title' => 'Most Valuable Player', 'rows' => []],
            ['title' => 'Sixth Man of the Year', 'rows' => []],
            ['title' => 'Rookie of the Year', 'rows' => []],
            ['title' => 'GM of the Year', 'rows' => []],
        ]);

        /** @var array<string, list<string>> $awardsByTeam */
        $awardsByTeam = [];
        $this->stubRepository->method('upsertAward')
            ->willReturnCallback(function (int $year, string $award, string $name) use (&$awardsByTeam): int {
                if (str_starts_with($award, 'All-Defensive')) {
                    $awardsByTeam[$award][] = $name;
                }
                return 1;
            });

        $leadersPath = $this->writeLeadersHtmWithTeams();
        $this->service->generateSeasonAwards(2025, $leadersPath);

        $this->assertSame(
            ['All Defense0P1', 'All Defense0P2', 'All Defense0P3', 'All Defense0P4', 'All Defense0P5'],
            $awardsByTeam['All-Defensive Team (1st)'] ?? []
        );
        $this->assertSame(
            ['All Defense1P1', 'All Defense1P2', 'All Defense1P3', 'All Defense1P4', 'All Defense1P5'],
            $awardsByTeam['All-Defensive Team (2nd)'] ?? []
        );
        $this->assertSame(
            ['All Defense2P1', 'All Defense2P2', 'All Defense2P3', 'All Defense2P4', 'All Defense2P5'],
            $awardsByTeam['All-Defensive Team (3rd)'] ?? []
        );
    }

    public function testSuccessMessageReportsExactCounts(): void
    {
        $this->stubVotingService->method('getEndOfYearResults')->willReturn([
            ['title' => 'Most Valuable Player', 'rows' => []],
            ['title' => 'Sixth Man of the Year', 'rows' => []],
            ['title' => 'Rookie of the Year', 'rows' => []],
            [
                'title' => 'GM of the Year',
                'rows' => [
                    ['name' => 'BestGM', 'votes' => 20, 'pid' => 0],
                ],
            ],
        ]);

        $this->stubRepository->method('upsertGmAward')->willReturn(1);
        $awardCalls = 0;
        $this->stubRepository->method('upsertAward')
            ->willReturnCallback(function () use (&$awardCalls): int {
                $awardCalls++;
                return $awardCalls === 1 ? 1 : 2;
            });

        $leadersPath = $this->writeMinimalLeadersHtm();
        $result = $this->service->generateSeasonAwards(2025, $leadersPath);

        $this->assertSame('Season awards generated: 2 inserted, 74 already existed. Total: 76.', $result['message']);
    }

    public function testConstructorTakesOnlyRepositoryAndVotingService(): void
    {
        $constructor = (new \ReflectionClass(LeagueControlPanelAwardGenerationService::class))->getConstructor();
        $this->assertNotNull($constructor);

        $typeNames = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            $this->assertInstanceOf(\ReflectionNamedType::class, $type);
            $typeNames[] = $type->getName();
        }

        $this->assertSame(
            [LeagueControlPanelRepositoryInterface::class, VotingResultsServiceInterface::class],
            $typeNames
        );
    }

    public function testServiceHoldsOnlyRepositoryAndVotingServiceProperties(): void
    {
        $propertyTypes = [];
        foreach ((new \ReflectionClass(LeagueControlPanelAwardGenerationService::class))->getProperties() as $prop) {
            $type = $prop->getType();
            $this->assertInstanceOf(\ReflectionNamedType::class, $type);
            $propertyTypes[$prop->getName()] = $type->getName();
        }

        $this->assertSame(
            [
                'repository' => LeagueControlPanelRepositoryInterface::class,
                'votingResultsService' => VotingResultsServiceInterface::class,
            ],
            $propertyTypes
        );
    }

    public function testOnlyPublicMethodIsGenerateSeasonAwards(): void
    {
        $names = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            (new \ReflectionClass(LeagueControlPanelAwardGenerationService::class))
                ->getMethods(\ReflectionMethod::IS_PUBLIC)
        );
        sort($names);

        $this->assertSame(['__construct', 'generateSeasonAwards'], $names);
    }

    public function testServiceCallsOnlyUpsertRepositoryMethods(): void
    {
        $mockRepository = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mockRepository->expects($this->never())->method(
            self::logicalNot(self::logicalOr(self::equalTo('upsertAward'), self::equalTo('upsertGmAward')))
        );
        $mockRepository->method('upsertAward')->willReturn(1);
        $mockRepository->method('upsertGmAward')->willReturn(1);

        $stubVoting = self::createStub(VotingResultsServiceInterface::class);
        $stubVoting->method('getEndOfYearResults')->willReturn([
            [
                'title' => 'Most Valuable Player',
                'rows' => [['name' => 'Player1, T1', 'votes' => 10, 'pid' => 1]],
            ],
            ['title' => 'Sixth Man of the Year', 'rows' => []],
            ['title' => 'Rookie of the Year', 'rows' => []],
            [
                'title' => 'GM of the Year',
                'rows' => [['name' => 'BestGM', 'votes' => 5, 'pid' => 0]],
            ],
        ]);

        $service = new LeagueControlPanelAwardGenerationService($mockRepository, $stubVoting);
        $result = $service->generateSeasonAwards(2025, $this->writeMinimalLeadersHtm());

        $this->assertTrue($result['success']);
    }

    public function testEmptyVoteCategoriesReportZeroInsertedWhenAllAwardsExist(): void
    {
        $this->stubVotingService->method('getEndOfYearResults')->willReturn([
            ['title' => 'Most Valuable Player', 'rows' => []],
            ['title' => 'Sixth Man of the Year', 'rows' => []],
            ['title' => 'Rookie of the Year', 'rows' => []],
            ['title' => 'GM of the Year', 'rows' => []],
        ]);

        $awardCalls = 0;
        $this->stubRepository->method('upsertAward')
            ->willReturnCallback(function () use (&$awardCalls): int {
                $awardCalls++;
                return 2;
            });

        $leadersPath = $this->writeMinimalLeadersHtm();
        $result = $this->service->generateSeasonAwards(2025, $leadersPath);

        $this->assertSame(0, $result['inserted']);
        $this->assertSame($awardCalls, $result['skipped']);
    }

    /**
     * Write a minimal Leaders.htm with all required sections (empty players).
     */
    private function writeMinimalLeadersHtm(): string
    {
        $html = '<html><body><pre><table>';

        // DPOY
        $html .= '<tr><th>po</th><th>defensive player</th><th>team</th></tr>';
        for ($i = 1; $i <= 5; $i++) {
            $html .= '<tr><td CLASS=tdp>PG</td><td CLASS=tdp>DPOY' . $i . '</td><td>Team' . $i . '</td></tr>';
        }

        // Stat leaders
        foreach (['scoring leader', 'rebound leader', 'assists leader', 'steals leader', 'blocks leader'] as $stat) {
            $html .= '<tr><th>po</th><th>' . $stat . '</th><th>team</th><th>ppg</th></tr>';
            for ($i = 1; $i <= 5; $i++) {
                $html .= '<tr><td CLASS=tdp>PG</td><td CLASS=tdp>Stat' . $i . '</td><td>Team' . $i . '</td><td>10.0</td></tr>';
            }
        }

        // Teams
        foreach (['All League', 'All Defense', 'All Rookie'] as $prefix) {
            $html .= '<tr><th></th><th>' . $prefix . ' 1st</th><th></th><th></th><th>' . $prefix . ' 2nd</th><th></th><th></th><th>' . $prefix . ' 3rd</th><th></th></tr>';
            $html .= '<tr><th>po</th><th>player</th><th>team</th><th>po</th><th>player</th><th>team</th><th>po</th><th>player</th><th>team</th></tr>';
            for ($i = 1; $i <= 5; $i++) {
                $html .= '<tr>';
                for ($t = 0; $t < 3; $t++) {
                    $teamNum = $t * 5 + $i;
                    $html .= '<td CLASS=tdp>PG</td><td CLASS=tdp>' . $prefix . 'P' . $teamNum . '</td><td>T' . $teamNum . '</td>';
                }
                $html .= '</tr>';
            }
        }

        $html .= '</table></pre></body></html>';
        $path = $this->tempDir . '/Leaders.htm';
        file_put_contents($path, $html);
        return $path;
    }

    /**
     * Write Leaders.htm with known All-League teams for testing fill logic.
     */
    private function writeLeadersHtmWithTeams(): string
    {
        $html = '<html><body><pre><table>';

        // DPOY (required)
        $html .= '<tr><th>po</th><th>defensive player</th><th>team</th></tr>';
        for ($i = 1; $i <= 5; $i++) {
            $html .= '<tr><td CLASS=tdp>PG</td><td CLASS=tdp>DPOY' . $i . '</td><td>T</td></tr>';
        }

        // Stat leaders (required)
        foreach (['scoring leader', 'rebound leader', 'assists leader', 'steals leader', 'blocks leader'] as $stat) {
            $html .= '<tr><th>po</th><th>' . $stat . '</th><th>team</th><th>ppg</th></tr>';
            for ($i = 1; $i <= 5; $i++) {
                $html .= '<tr><td CLASS=tdp>PG</td><td CLASS=tdp>S' . $i . '</td><td>T</td><td>10.0</td></tr>';
            }
        }

        // All League teams — JSB fill players
        $html .= '<tr><th></th><th>All League 1st</th><th></th><th></th><th>All League 2nd</th><th></th><th></th><th>All League 3rd</th><th></th></tr>';
        $html .= '<tr><th>po</th><th>player</th><th>team</th><th>po</th><th>player</th><th>team</th><th>po</th><th>player</th><th>team</th></tr>';
        // JSB fill names that don't overlap with vote-getters
        $jsbNames = [
            ['JsbA1', 'JsbB1', 'JsbC1'],
            ['JsbA2', 'JsbB2', 'JsbC2'],
            ['JsbA3', 'JsbB3', 'JsbC3'],
            ['JsbA4', 'JsbB4', 'JsbC4'],
            ['JsbA5', 'JsbB5', 'JsbC5'],
        ];
        foreach ($jsbNames as $row) {
            $html .= '<tr>';
            foreach ($row as $name) {
                $html .= '<td CLASS=tdp>PG</td><td CLASS=tdp>' . $name . '</td><td>T</td>';
            }
            $html .= '</tr>';
        }

        // All Defense and All Rookie (required)
        foreach (['All Defense', 'All Rookie'] as $prefix) {
            $html .= '<tr><th></th><th>' . $prefix . ' 1st</th><th></th><th></th><th>' . $prefix . ' 2nd</th><th></th><th></th><th>' . $prefix . ' 3rd</th><th></th></tr>';
            $html .= '<tr><th>po</th><th>player</th><th>team</th><th>po</th><th>player</th><th>team</th><th>po</th><th>player</th><th>team</th></tr>';
            for ($i = 1; $i <= 5; $i++) {
                $html .= '<tr>';
                for ($t = 0; $t < 3; $t++) {
                    $html .= '<td CLASS=tdp>PG</td><td CLASS=tdp>' . $prefix . $t . 'P' . $i . '</td><td>T</td>';
                }
                $html .= '</tr>';
            }
        }

        $html .= '</table></pre></body></html>';
        $path = $this->tempDir . '/Leaders.htm';
        file_put_contents($path, $html);
        return $path;
    }

    /**
     * Write Leaders.htm where All-League 1st team contains "Player1" (duplicate with votes).
     */
    private function writeLeadersHtmWithDuplicatePlayer(): string
    {
        $html = '<html><body><pre><table>';

        // DPOY
        $html .= '<tr><th>po</th><th>defensive player</th><th>team</th></tr>';
        for ($i = 1; $i <= 5; $i++) {
            $html .= '<tr><td CLASS=tdp>PG</td><td CLASS=tdp>D' . $i . '</td><td>T</td></tr>';
        }

        // Stat leaders
        foreach (['scoring leader', 'rebound leader', 'assists leader', 'steals leader', 'blocks leader'] as $stat) {
            $html .= '<tr><th>po</th><th>' . $stat . '</th><th>team</th><th>ppg</th></tr>';
            for ($i = 1; $i <= 5; $i++) {
                $html .= '<tr><td CLASS=tdp>PG</td><td CLASS=tdp>S' . $i . '</td><td>T</td><td>10.0</td></tr>';
            }
        }

        // All League — Player1 appears in JSB 1st team (will duplicate with votes)
        $html .= '<tr><th></th><th>All League 1st</th><th></th><th></th><th>All League 2nd</th><th></th><th></th><th>All League 3rd</th><th></th></tr>';
        $html .= '<tr><th>po</th><th>player</th><th>team</th><th>po</th><th>player</th><th>team</th><th>po</th><th>player</th><th>team</th></tr>';
        $names1 = ['Player1', 'JsbA2', 'JsbA3', 'JsbA4', 'JsbA5'];
        $names2 = ['JsbB1', 'JsbB2', 'JsbB3', 'JsbB4', 'JsbB5'];
        $names3 = ['JsbC1', 'JsbC2', 'JsbC3', 'JsbC4', 'JsbC5'];
        for ($i = 0; $i < 5; $i++) {
            $html .= '<tr><td CLASS=tdp>PG</td><td CLASS=tdp>' . $names1[$i] . '</td><td>T</td>';
            $html .= '<td CLASS=tdp>PG</td><td CLASS=tdp>' . $names2[$i] . '</td><td>T</td>';
            $html .= '<td CLASS=tdp>PG</td><td CLASS=tdp>' . $names3[$i] . '</td><td>T</td></tr>';
        }

        // All Defense and All Rookie
        foreach (['All Defense', 'All Rookie'] as $prefix) {
            $html .= '<tr><th></th><th>' . $prefix . ' 1st</th><th></th><th></th><th>' . $prefix . ' 2nd</th><th></th><th></th><th>' . $prefix . ' 3rd</th><th></th></tr>';
            $html .= '<tr><th>po</th><th>player</th><th>team</th><th>po</th><th>player</th><th>team</th><th>po</th><th>player</th><th>team</th></tr>';
            for ($i = 1; $i <= 5; $i++) {
                $html .= '<tr>';
                for ($t = 0; $t < 3; $t++) {
                    $html .= '<td CLASS=tdp>PG</td><td CLASS=tdp>' . $prefix . $t . 'P' . $i . '</td><td>T</td>';
                }
                $html .= '</tr>';
            }
        }

        $html .= '</table></pre></body></html>';
        $path = $this->tempDir . '/Leaders.htm';
        file_put_contents($path, $html);
        return $path;
    }
}
