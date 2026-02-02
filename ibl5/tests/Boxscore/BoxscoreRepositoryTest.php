<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\BoxscoreRepository;
use Boxscore\Contracts\BoxscoreRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Mocks\MockDatabase;

/**
 * @covers \Boxscore\BoxscoreRepository
 */
class BoxscoreRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;
    private BoxscoreRepository $repository;
    private string|false $previousErrorLog = false;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->repository = new BoxscoreRepository($this->mockDb);
    }

    protected function tearDown(): void
    {
        if ($this->previousErrorLog !== false) {
            ini_set('error_log', $this->previousErrorLog);
            $this->previousErrorLog = false;
        }
        parent::tearDown();
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(BoxscoreRepositoryInterface::class, $this->repository);
    }

    public function testDeletePreseasonBoxScoresExecutesTwoQueries(): void
    {
        $this->mockDb->setReturnTrue(true);

        $result = $this->repository->deletePreseasonBoxScores();

        $this->assertTrue($result);
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(2, $queries);
        $this->assertStringContainsString('DELETE FROM ibl_box_scores', $queries[0]);
        $this->assertStringContainsString('DELETE FROM ibl_box_scores_teams', $queries[1]);
    }

    public function testDeletePreseasonBoxScoresUsesCorrectDateRange(): void
    {
        $this->mockDb->setReturnTrue(true);

        $this->repository->deletePreseasonBoxScores();

        $queries = $this->mockDb->getExecutedQueries();
        // Preseason year is 9998, November dates
        $this->assertStringContainsString('9998-11-01', $queries[0]);
        $this->assertStringContainsString('9998-11-30', $queries[0]);
    }

    public function testDeleteHeatBoxScoresUsesCorrectDateRange(): void
    {
        $this->mockDb->setReturnTrue(true);

        $this->repository->deleteHeatBoxScores(2024);

        $queries = $this->mockDb->getExecutedQueries();
        // HEAT month is October (10)
        $this->assertStringContainsString('2024-10-01', $queries[0]);
        $this->assertStringContainsString('2024-10-31', $queries[0]);
    }

    public function testDeleteRegularSeasonAndPlayoffsBoxScoresUsesCorrectDateRange(): void
    {
        $this->mockDb->setReturnTrue(true);

        $this->repository->deleteRegularSeasonAndPlayoffsBoxScores(2024);

        $queries = $this->mockDb->getExecutedQueries();
        // Regular season starts November (11), playoffs end June (06)
        $this->assertStringContainsString('2024-11-01', $queries[0]);
        $this->assertStringContainsString('2025-06-30', $queries[0]);
    }

    public function testDeleteThrowsExceptionOnFailure(): void
    {
        $this->mockDb->setReturnTrue(false);
        $this->previousErrorLog = ini_get('error_log') ?: '';
        ini_set('error_log', '/dev/null');

        $this->expectException(\RuntimeException::class);
        $this->repository->deletePreseasonBoxScores();
    }
}
