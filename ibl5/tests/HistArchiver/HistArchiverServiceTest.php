<?php

declare(strict_types=1);

namespace Tests\HistArchiver;

use HistArchiver\Contracts\HistArchiverRepositoryInterface;
use HistArchiver\HistArchiverService;
use HistArchiver\HistArchiveResult;
use HistArchiver\PlrValidationReport;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HistArchiverServiceTest extends TestCase
{
    private HistArchiverRepositoryInterface&MockObject $repository;
    private HistArchiverService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(HistArchiverRepositoryInterface::class);
        $this->service = new HistArchiverService($this->repository);
    }

    public function testArchiveSkipsWhenNoChampion(): void
    {
        $this->repository->expects($this->once())->method('hasChampionForYear')->with(2026)->willReturn(false);
        $this->repository->expects($this->never())->method('getRegularSeasonTotals');

        $result = $this->service->archiveSeason(2026);

        $this->assertTrue($result->skippedNoChampion);
        $this->assertSame(0, $result->playersArchived);
        $this->assertSame(0, $result->rowsUpserted);
    }

    public function testArchiveSkipsPlayerNotInPlr(): void
    {
        $this->repository->method('hasChampionForYear')->willReturn(true);
        $this->repository->method('getRegularSeasonTotals')->willReturn([
            $this->makePlayerRow(1, 'Ghost Player'),
        ]);
        $this->repository->expects($this->once())->method('getPlayerRatingsAndContract')->with(1)->willReturn(null);
        $this->repository->expects($this->never())->method('upsertHistRow');

        $result = $this->service->archiveSeason(2026);

        $this->assertFalse($result->skippedNoChampion);
        $this->assertSame(0, $result->playersArchived);
        $this->assertCount(1, $result->messages);
        $this->assertStringContainsString('WARNING', $result->messages[0]);
        $this->assertStringContainsString('Ghost Player', $result->messages[0]);
    }

    public function testArchiveInsertsCorrectData(): void
    {
        $this->repository->method('hasChampionForYear')->willReturn(true);
        $this->repository->method('getRegularSeasonTotals')->willReturn([
            $this->makePlayerRow(100, 'John Doe', games: 82, pts: 1500),
        ]);
        $this->repository->expects($this->once())->method('getPlayerRatingsAndContract')->with(100)->willReturn(
            $this->makeRatingsArray(tid: 5, salary: 3000),
        );

        $capturedData = null;
        $this->repository->method('upsertHistRow')->willReturnCallback(
            static function (array $data) use (&$capturedData): int {
                $capturedData = $data;
                return 1;
            },
        );

        $this->service->archiveSeason(2026);

        $this->assertNotNull($capturedData);
        $this->assertSame(100, $capturedData['pid']);
        $this->assertSame('John Doe', $capturedData['name']);
        $this->assertSame(2026, $capturedData['year']);
        $this->assertSame(82, $capturedData['games']);
        $this->assertSame(1500, $capturedData['pts']);
        $this->assertSame(5, $capturedData['teamid']);
        $this->assertSame(3000, $capturedData['salary']);
        // Ratings from ibl_plr mapped to hist column names
        $this->assertSame(50, $capturedData['r_oo']);
        $this->assertSame(40, $capturedData['r_2ga']);
    }

    public function testArchiveReturnsCorrectCount(): void
    {
        $this->repository->method('hasChampionForYear')->willReturn(true);
        $this->repository->method('getRegularSeasonTotals')->willReturn([
            $this->makePlayerRow(1, 'Player One'),
            $this->makePlayerRow(2, 'Player Two'),
            $this->makePlayerRow(3, 'Player Three'),
        ]);
        $this->repository->method('getPlayerRatingsAndContract')->willReturn(
            $this->makeRatingsArray(),
        );
        $this->repository->expects($this->exactly(3))->method('upsertHistRow')->willReturn(1);

        $result = $this->service->archiveSeason(2026);

        $this->assertSame(3, $result->playersArchived);
        $this->assertSame(3, $result->rowsUpserted);
        $this->assertSame([], $result->messages);
    }

    public function testArchiveIsIdempotent(): void
    {
        $this->repository->method('hasChampionForYear')->willReturn(true);
        $this->repository->method('getRegularSeasonTotals')->willReturn([
            $this->makePlayerRow(1, 'Player One'),
        ]);
        $this->repository->method('getPlayerRatingsAndContract')->willReturn(
            $this->makeRatingsArray(),
        );
        // ON DUPLICATE KEY UPDATE returns 2 for update, 1 for insert
        $this->repository->expects($this->exactly(2))->method('upsertHistRow')
            ->willReturnOnConsecutiveCalls(1, 2);

        $result1 = $this->service->archiveSeason(2026);
        $result2 = $this->service->archiveSeason(2026);

        $this->assertSame(1, $result1->playersArchived);
        $this->assertSame(1, $result2->playersArchived);
    }

    public function testValidateNoDiscrepancies(): void
    {
        $this->repository->expects($this->once())->method('getValidationComparison')->with(2026)->willReturn([
            $this->makeComparisonRow(1, 'Player One', games: 82, pts: 1500),
        ]);

        $report = $this->service->validatePlrVsBoxScores(2026);

        $this->assertSame(1, $report->totalPlayers);
        $this->assertSame(1, $report->matchCount);
        $this->assertSame(0, $report->getDiscrepancyCount());
    }

    public function testValidateWithEmptyResultSet(): void
    {
        $this->repository->expects($this->once())->method('getValidationComparison')->with(2026)->willReturn([]);

        $report = $this->service->validatePlrVsBoxScores(2026);

        $this->assertSame(0, $report->totalPlayers);
        $this->assertSame(0, $report->matchCount);
        $this->assertSame(0, $report->getDiscrepancyCount());
    }

    public function testValidateMultiplePlayersWithMixedResults(): void
    {
        $this->repository->expects($this->once())->method('getValidationComparison')->with(2026)->willReturn([
            $this->makeComparisonRow(1, 'Player One'),
            $this->makeComparisonRow(2, 'Player Two'),
            $this->makeComparisonRowWithDiff(3, 'Player Three', column: 'pts', histValue: 1500, bsValue: 1502),
        ]);

        $report = $this->service->validatePlrVsBoxScores(2026);

        $this->assertSame(3, $report->totalPlayers);
        $this->assertSame(2, $report->matchCount);
        $this->assertSame(1, $report->getDiscrepancyCount());
    }

    public function testValidateMultipleDiscrepanciesOnSamePlayer(): void
    {
        $row = $this->makeComparisonRow(1, 'Player One');
        $row['hist_pts'] = 1500;
        $row['bs_pts'] = 1502;
        $row['hist_games'] = 82;
        $row['bs_games'] = 80;
        $this->repository->expects($this->once())->method('getValidationComparison')->with(2026)->willReturn([$row]);

        $report = $this->service->validatePlrVsBoxScores(2026);

        $this->assertSame(1, $report->totalPlayers);
        $this->assertSame(0, $report->matchCount);
        $this->assertSame(2, $report->getDiscrepancyCount());
    }

    public function testArchiveWithNoBoxScores(): void
    {
        $this->repository->method('hasChampionForYear')->willReturn(true);
        $this->repository->method('getRegularSeasonTotals')->willReturn([]);
        $this->repository->expects($this->never())->method('upsertHistRow');

        $result = $this->service->archiveSeason(2026);

        $this->assertFalse($result->skippedNoChampion);
        $this->assertSame(0, $result->playersArchived);
        $this->assertSame(0, $result->rowsUpserted);
    }

    public function testValidateDetectsDiscrepancy(): void
    {
        $this->repository->expects($this->once())->method('getValidationComparison')->with(2026)->willReturn([
            $this->makeComparisonRowWithDiff(1, 'Player One', column: 'pts', histValue: 1500, bsValue: 1502),
        ]);

        $report = $this->service->validatePlrVsBoxScores(2026);

        $this->assertSame(1, $report->totalPlayers);
        $this->assertSame(0, $report->matchCount);
        $this->assertSame(1, $report->getDiscrepancyCount());
        $this->assertSame('pts', $report->discrepancies[0]['column']);
        $this->assertSame(1500, $report->discrepancies[0]['hist_value']);
        $this->assertSame(1502, $report->discrepancies[0]['box_score_value']);
    }

    /**
     * @return array<string, int|string>
     */
    private function makePlayerRow(int $pid, string $name, int $games = 50, int $pts = 800): array
    {
        return [
            'year' => 2026, 'pos' => 1, 'pid' => $pid, 'name' => $name, 'team' => 'Test Team',
            'games' => $games, 'minutes' => 2000, 'fgm' => 300, 'fga' => 600,
            'ftm' => 150, 'fta' => 180, 'tgm' => 50, 'tga' => 130,
            'orb' => 80, 'reb' => 400, 'ast' => 200, 'stl' => 60,
            'tvr' => 120, 'blk' => 30, 'pf' => 150, 'pts' => $pts,
        ];
    }

    /**
     * @return array{tid: int, r_2ga: int, r_2gp: int, r_fta: int, r_ftp: int, r_3ga: int, r_3gp: int, r_orb: int, r_drb: int, r_ast: int, r_stl: int, r_blk: int, r_tvr: int, r_oo: int, r_od: int, r_do: int, r_dd: int, r_po: int, r_pd: int, r_to: int, r_td: int, salary: int}
     */
    private function makeRatingsArray(int $tid = 1, int $salary = 1000): array
    {
        return [
            'tid' => $tid, 'salary' => $salary,
            'r_2ga' => 40, 'r_2gp' => 45, 'r_fta' => 30, 'r_ftp' => 80,
            'r_3ga' => 20, 'r_3gp' => 35, 'r_orb' => 25, 'r_drb' => 50,
            'r_ast' => 60, 'r_stl' => 40, 'r_blk' => 15, 'r_tvr' => 30,
            'r_oo' => 50, 'r_od' => 45, 'r_do' => 55, 'r_dd' => 40,
            'r_po' => 60, 'r_pd' => 35, 'r_to' => 50, 'r_td' => 45,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function makeComparisonRow(int $pid, string $name, int $games = 82, int $pts = 1500): array
    {
        $row = ['pid' => $pid, 'name' => $name];
        $stats = [
            'games' => $games, 'minutes' => 2000, 'fgm' => 300, 'fga' => 600,
            'ftm' => 150, 'fta' => 180, 'tgm' => 50, 'tga' => 130,
            'orb' => 80, 'reb' => 400, 'ast' => 200, 'stl' => 60,
            'tvr' => 120, 'blk' => 30, 'pf' => 150, 'pts' => $pts,
        ];
        foreach ($stats as $col => $val) {
            $row['hist_' . $col] = $val;
            $row['bs_' . $col] = $val;
        }
        return $row;
    }

    /**
     * @return array<string, int|string>
     */
    private function makeComparisonRowWithDiff(int $pid, string $name, string $column, int $histValue, int $bsValue): array
    {
        $row = $this->makeComparisonRow($pid, $name);
        $row['hist_' . $column] = $histValue;
        $row['bs_' . $column] = $bsValue;
        return $row;
    }
}
