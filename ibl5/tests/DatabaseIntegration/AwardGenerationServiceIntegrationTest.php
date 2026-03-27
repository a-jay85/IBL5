<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use LeagueControlPanel\AwardGenerationService;
use LeagueControlPanel\LeagueControlPanelRepository;
use PHPUnit\Framework\Attributes\Group;
use Voting\Contracts\VotingResultsServiceInterface;

/**
 * Integration tests for AwardGenerationService::generateSeasonAwards() against real MariaDB.
 *
 * @covers \LeagueControlPanel\AwardGenerationService
 */
#[Group('database')]
class AwardGenerationServiceIntegrationTest extends DatabaseTestCase
{
    private AwardGenerationService $service;

    /** @var VotingResultsServiceInterface&\PHPUnit\Framework\MockObject\Stub */
    private VotingResultsServiceInterface $stubVoting;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = new LeagueControlPanelRepository($this->db);
        $this->stubVoting = $this->createStub(VotingResultsServiceInterface::class);
        $this->service = new AwardGenerationService($repository, $this->stubVoting);

        $this->tempDir = sys_get_temp_dir() . '/award_gen_int_' . uniqid();
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
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    public function testInsertsAwardsIntoDatabase(): void
    {
        $this->stubVotingWith5MvpAnd1Gm();
        $leadersPath = $this->writeLeadersHtm();

        $result = $this->service->generateSeasonAwards(8888, $leadersPath);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['inserted']);

        $awardCount = $this->countAwards(8888);
        $gmCount = $this->countGmAwards(8888);
        $this->assertSame(1, $gmCount);
        // GM award is counted in result totals but stored in ibl_gm_awards
        $this->assertSame($result['inserted'] + $result['skipped'], $awardCount + $gmCount);
    }

    public function testWritesMvpAwardFromVotes(): void
    {
        $this->stubVoting->method('getEndOfYearResults')->willReturn([
            [
                'title' => 'Most Valuable Player',
                'rows' => [
                    ['name' => 'Test MVP, Lakers', 'votes' => 20, 'pid' => 1],
                ],
            ],
            ['title' => 'Sixth Man of the Year', 'rows' => []],
            ['title' => 'Rookie of the Year', 'rows' => []],
            ['title' => 'GM of the Year', 'rows' => []],
        ]);

        $leadersPath = $this->writeLeadersHtm();
        $this->service->generateSeasonAwards(8888, $leadersPath);

        $rows = $this->queryAwards(8888, 'Most Valuable Player (1st)');
        $this->assertCount(1, $rows);
        $this->assertSame('Test MVP', $rows[0]['name']);
    }

    public function testWritesGmAwardToGmAwardsTable(): void
    {
        $this->stubVoting->method('getEndOfYearResults')->willReturn([
            ['title' => 'Most Valuable Player', 'rows' => []],
            ['title' => 'Sixth Man of the Year', 'rows' => []],
            ['title' => 'Rookie of the Year', 'rows' => []],
            [
                'title' => 'GM of the Year',
                'rows' => [
                    ['name' => 'TestGM', 'votes' => 10, 'pid' => 0],
                ],
            ],
        ]);

        $leadersPath = $this->writeLeadersHtm();
        $this->service->generateSeasonAwards(8888, $leadersPath);

        $rows = $this->queryGmAwards(8888);
        $this->assertCount(1, $rows);
        $this->assertSame('TestGM', $rows[0]['name']);
    }

    public function testWritesDpoyFromLeadersHtm(): void
    {
        $this->stubVotingEmpty();
        $leadersPath = $this->writeLeadersHtm();

        $this->service->generateSeasonAwards(8888, $leadersPath);

        $rows = $this->queryAwards(8888, 'Defensive Player of the Year (1st)');
        $this->assertCount(1, $rows);
        $this->assertSame('IntDPOY1', $rows[0]['name']);
    }

    public function testWritesStatLeadersFromLeadersHtm(): void
    {
        $this->stubVotingEmpty();
        $leadersPath = $this->writeLeadersHtm();

        $this->service->generateSeasonAwards(8888, $leadersPath);

        $rows = $this->queryAwards(8888, 'Scoring Leader (1st)');
        $this->assertCount(1, $rows);
        $this->assertSame('IntScorer1', $rows[0]['name']);
    }

    public function testIsIdempotentOnSecondRun(): void
    {
        $this->stubVotingEmpty();
        $leadersPath = $this->writeLeadersHtm();

        $first = $this->service->generateSeasonAwards(8888, $leadersPath);
        $this->assertGreaterThan(0, $first['inserted']);
        $this->assertSame(0, $first['skipped']);

        $countAfterFirst = $this->countAwards(8888);

        $second = $this->service->generateSeasonAwards(8888, $leadersPath);
        $this->assertSame(0, $second['inserted']);
        $this->assertGreaterThan(0, $second['skipped']);

        $countAfterSecond = $this->countAwards(8888);
        $this->assertSame($countAfterFirst, $countAfterSecond, 'Row count should not change on second run');
    }

    public function testNoPartialWritesOnMissingLeadersHtm(): void
    {
        $this->stubVotingEmpty();

        $result = $this->service->generateSeasonAwards(8888, '/nonexistent/Leaders.htm');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to parse', $result['message']);
        $this->assertSame(0, $this->countAwards(8888));
    }

    public function testWritesAllTeamAwards(): void
    {
        $this->stubVotingWith5MvpAnd1Gm();
        $leadersPath = $this->writeLeadersHtm();

        $this->service->generateSeasonAwards(8888, $leadersPath);

        $allLeague = $this->queryAwardsLike(8888, 'All-League%');
        $this->assertSame(15, count($allLeague), 'Expected 15 All-League awards (3 teams × 5)');
    }

    // --- Voting stubs ---

    private function stubVotingEmpty(): void
    {
        $this->stubVoting->method('getEndOfYearResults')->willReturn([
            ['title' => 'Most Valuable Player', 'rows' => []],
            ['title' => 'Sixth Man of the Year', 'rows' => []],
            ['title' => 'Rookie of the Year', 'rows' => []],
            ['title' => 'GM of the Year', 'rows' => []],
        ]);
    }

    private function stubVotingWith5MvpAnd1Gm(): void
    {
        $this->stubVoting->method('getEndOfYearResults')->willReturn([
            [
                'title' => 'Most Valuable Player',
                'rows' => [
                    ['name' => 'IntMVP1, Team1', 'votes' => 20, 'pid' => 1],
                    ['name' => 'IntMVP2, Team2', 'votes' => 18, 'pid' => 2],
                    ['name' => 'IntMVP3, Team3', 'votes' => 15, 'pid' => 3],
                    ['name' => 'IntMVP4, Team4', 'votes' => 12, 'pid' => 4],
                    ['name' => 'IntMVP5, Team5', 'votes' => 10, 'pid' => 5],
                ],
            ],
            ['title' => 'Sixth Man of the Year', 'rows' => []],
            ['title' => 'Rookie of the Year', 'rows' => []],
            [
                'title' => 'GM of the Year',
                'rows' => [
                    ['name' => 'IntTestGM', 'votes' => 8, 'pid' => 0],
                ],
            ],
        ]);
    }

    // --- Leaders.htm fixture ---

    private function writeLeadersHtm(): string
    {
        $html = '<html><body><pre><table>';

        // DPOY
        $html .= '<tr><th>po</th><th>defensive player</th><th>team</th></tr>';
        for ($i = 1; $i <= 5; $i++) {
            $html .= '<tr><td CLASS=tdp>PG</td><td CLASS=tdp>IntDPOY' . $i . '</td><td>Team' . $i . '</td></tr>';
        }

        // Stat leaders
        $statPrefixes = [
            'scoring leader' => 'IntScorer',
            'rebound leader' => 'IntRebounder',
            'assists leader' => 'IntAssister',
            'steals leader' => 'IntStealer',
            'blocks leader' => 'IntBlocker',
        ];
        foreach ($statPrefixes as $stat => $prefix) {
            $html .= '<tr><th>po</th><th>' . $stat . '</th><th>team</th><th>ppg</th></tr>';
            for ($i = 1; $i <= 5; $i++) {
                $html .= '<tr><td CLASS=tdp>PG</td><td CLASS=tdp>' . $prefix . $i . '</td><td>Team' . $i . '</td><td>10.0</td></tr>';
            }
        }

        // Teams (All League, All Defense, All Rookie)
        foreach (['All League', 'All Defense', 'All Rookie'] as $prefix) {
            $html .= '<tr><th></th><th>' . $prefix . ' 1st</th><th></th><th></th><th>' . $prefix . ' 2nd</th><th></th><th></th><th>' . $prefix . ' 3rd</th><th></th></tr>';
            $html .= '<tr><th>po</th><th>player</th><th>team</th><th>po</th><th>player</th><th>team</th><th>po</th><th>player</th><th>team</th></tr>';
            for ($i = 1; $i <= 5; $i++) {
                $html .= '<tr>';
                for ($t = 0; $t < 3; $t++) {
                    $num = $t * 5 + $i;
                    $html .= '<td CLASS=tdp>PG</td><td CLASS=tdp>' . $prefix . 'P' . $num . '</td><td>T' . $num . '</td>';
                }
                $html .= '</tr>';
            }
        }

        $html .= '</table></pre></body></html>';
        $path = $this->tempDir . '/Leaders.htm';
        file_put_contents($path, $html);
        return $path;
    }

    // --- DB query helpers ---

    /**
     * @return list<array{year: int, Award: string, name: string}>
     */
    private function queryAwards(int $year, string $award): array
    {
        $stmt = $this->db->prepare("SELECT year, Award, name FROM ibl_awards WHERE year = ? AND Award = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('is', $year, $award);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            /** @var array{year: int, Award: string, name: string} $row */
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    /**
     * @return list<array{year: int, Award: string, name: string}>
     */
    private function queryAwardsLike(int $year, string $awardPattern): array
    {
        $stmt = $this->db->prepare("SELECT year, Award, name FROM ibl_awards WHERE year = ? AND Award LIKE ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('is', $year, $awardPattern);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            /** @var array{year: int, Award: string, name: string} $row */
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    /**
     * @return list<array{year: int, Award: string, name: string}>
     */
    private function queryGmAwards(int $year): array
    {
        $stmt = $this->db->prepare("SELECT year, Award, name FROM ibl_gm_awards WHERE year = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            /** @var array{year: int, Award: string, name: string} $row */
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    private function countAwards(int $year): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM ibl_awards WHERE year = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        self::assertNotNull($row);
        $stmt->close();
        return (int) $row['cnt'];
    }

    private function countGmAwards(int $year): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM ibl_gm_awards WHERE year = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        self::assertNotNull($row);
        $stmt->close();
        return (int) $row['cnt'];
    }
}
