<?php

declare(strict_types=1);

namespace Tests\LeagueControlPanel;

use LeagueControlPanel\AwardGenerationService;
use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Voting\Contracts\VotingResultsServiceInterface;

/**
 * @covers \LeagueControlPanel\AwardGenerationService
 */
class AwardGenerationServiceTest extends TestCase
{
    /** @var LeagueControlPanelRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private LeagueControlPanelRepositoryInterface $stubRepository;

    /** @var VotingResultsServiceInterface&\PHPUnit\Framework\MockObject\Stub */
    private VotingResultsServiceInterface $stubVotingService;

    private AwardGenerationService $service;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->stubRepository = $this->createStub(LeagueControlPanelRepositoryInterface::class);
        $this->stubVotingService = $this->createStub(VotingResultsServiceInterface::class);
        $this->service = new AwardGenerationService($this->stubRepository, $this->stubVotingService);
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
        $stubVotingService = $this->createStub(VotingResultsServiceInterface::class);
        $service = new AwardGenerationService($mockRepository, $stubVotingService);

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
