<?php

declare(strict_types=1);

namespace Tests\TrainingCampRatingsDiff;

use PHPUnit\Framework\TestCase;
use TrainingCampRatingsDiff\TrainingCampRatingsDiffRepository;
use Tests\WideUnit\Mocks\MockDatabase;

class TrainingCampRatingsDiffRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    // ---------------------------------------------------------------------------
    // getBaselinePhase()
    // ---------------------------------------------------------------------------

    public function testGetBaselinePhaseReturnsNullWhenNoSnapshotsExist(): void
    {
        $this->mockDb->onQuery('AS phase_rank', []);
        $repository = new TrainingCampRatingsDiffRepository($this->mockDb);

        $result = $repository->getBaselinePhase(2024);

        self::assertNull($result);
    }

    public function testGetBaselinePhaseReturnsEndOfSeasonWhenAvailable(): void
    {
        $this->mockDb->onQuery('AS phase_rank', [['snapshot_phase' => 'end-of-season']]);
        $repository = new TrainingCampRatingsDiffRepository($this->mockDb);

        $result = $repository->getBaselinePhase(2024);

        self::assertSame('end-of-season', $result);
    }

    public function testGetBaselinePhaseReturnsMidSeasonWhenOnlyMidSeasonAvailable(): void
    {
        $this->mockDb->onQuery('AS phase_rank', [['snapshot_phase' => 'mid-season']]);
        $repository = new TrainingCampRatingsDiffRepository($this->mockDb);

        $result = $repository->getBaselinePhase(2024);

        self::assertSame('mid-season', $result);
    }

    public function testGetBaselinePhaseReturnsNullForNullSnapshotPhaseColumn(): void
    {
        $this->mockDb->onQuery('AS phase_rank', [['snapshot_phase' => null]]);
        $repository = new TrainingCampRatingsDiffRepository($this->mockDb);

        $result = $repository->getBaselinePhase(2024);

        self::assertNull($result);
    }

    // ---------------------------------------------------------------------------
    // getDiffRows()
    // ---------------------------------------------------------------------------

    public function testGetDiffRowsQueriesLiveAndSnapshotTables(): void
    {
        $this->mockDb->setMockData([]);
        $repository = new TrainingCampRatingsDiffRepository($this->mockDb);

        $repository->getDiffRows(2024, 'end-of-season');

        $queries = $this->mockDb->getExecutedQueries();
        self::assertNotEmpty($queries);
        $combined = implode("\n", $queries);
        self::assertStringContainsString('ibl_plr', $combined);
        self::assertStringContainsString('ibl_plr_snapshots', $combined);
        // MockPreparedStatement substitutes bound params; both year and phase must appear
        self::assertStringContainsString('2024', $combined);
        self::assertStringContainsString('end-of-season', $combined);
        self::assertStringContainsString('retired = 0', $combined);
        // Verify ordering: season_year param appears before snapshot_phase param in the joined ON clause
        $joinPos   = strpos($combined, 'season_year');
        $phasePos  = strpos($combined, 'snapshot_phase');
        self::assertNotFalse($joinPos);
        self::assertNotFalse($phasePos);
        self::assertLessThan($phasePos, $joinPos, 'season_year bind must precede snapshot_phase bind in ON clause');
    }

    public function testGetDiffRowsAppliesFilterTidWhenSet(): void
    {
        $this->mockDb->setMockData([]);
        $repository = new TrainingCampRatingsDiffRepository($this->mockDb);

        $repository->getDiffRows(2024, 'end-of-season', 7);

        $queries = $this->mockDb->getExecutedQueries();
        $combined = implode("\n", $queries);
        // MockPreparedStatement substitutes bound parameters; the final query
        // should contain the literal teamid filter.
        self::assertStringContainsString('p.teamid = 7', $combined);
    }

    public function testGetDiffRowsReturnsRowsFromDatabase(): void
    {
        $row = ['pid' => 1, 'name' => 'Test Player', 'pos' => 'PG', 'teamid' => 5];
        $this->mockDb->setMockData([$row]);
        $repository = new TrainingCampRatingsDiffRepository($this->mockDb);

        $result = $repository->getDiffRows(2024, 'end-of-season');

        self::assertCount(1, $result);
        self::assertSame('Test Player', $result[0]['name']);
    }
}
