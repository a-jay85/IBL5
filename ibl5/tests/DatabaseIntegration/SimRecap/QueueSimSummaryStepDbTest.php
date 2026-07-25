<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\SimRecap;

use PHPUnit\Framework\Attributes\Group;
use Season\SeasonQueryRepository;
use SimRecap\SimSummaryRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Updater\Steps\QueueSimSummaryStep;

/**
 * Database integration test for QueueSimSummaryStep.
 *
 * Verifies that execute() queues a pending recap row for the current sim,
 * is idempotent on a second call, and returns rows in the shape the admin
 * viewer requires (recap_length alias, no recap body, ORDER BY sim DESC).
 *
 * Isolation: transaction rollback. Sim 9001 is outside the 686–689 seed
 * band asserted by unit 2, so this test cannot interfere with it.
 */
#[Group('database')]
final class QueueSimSummaryStepDbTest extends DatabaseTestCase
{
    private SimSummaryRepository $repo;
    private SeasonQueryRepository $seasonQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new SimSummaryRepository($this->db);
        $this->seasonQuery = new SeasonQueryRepository($this->db);

        // Insert an ibl_sim_dates row so getLastSimDatesArray() returns sim 9001
        // (highest value in the table within this transaction).
        $this->insertRow('ibl_sim_dates', [
            'sim'        => 9001,
            'start_date' => '2099-01-01',
            'end_date'   => '2099-01-07',
        ]);
    }

    /**
     * execute() queues a pending row for the current sim.
     *
     * Verification matrix row 35.
     */
    public function testProducerStepCreatesAPendingRow(): void
    {
        $step = new QueueSimSummaryStep($this->repo, $this->seasonQuery);
        $step->execute();

        $row = $this->repo->find(9001);
        self::assertNotNull($row, 'find(9001) must return a row after execute()');
        self::assertSame('pending', $row['status'], 'Newly queued row must have status=pending');
        self::assertSame(0, $row['attempts'], 'Newly queued row must have attempts=0');
    }

    /**
     * A second execute() call leaves exactly one row (queuePendingIfAbsent is idempotent).
     *
     * Verification matrix row 36.
     */
    public function testSecondExecuteDoesNotCreateDuplicateRow(): void
    {
        $step = new QueueSimSummaryStep($this->repo, $this->seasonQuery);
        $step->execute();
        $step->execute();

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS cnt FROM `ibl_sim_summaries` WHERE `sim` = 9001'
        );
        self::assertNotFalse($stmt, 'Prepare must succeed: ' . $this->db->error);
        $stmt->execute();
        $result = $stmt->get_result();
        /** @var array{cnt: int}|null $countRow */
        $countRow = $result->fetch_assoc();
        $stmt->close();

        self::assertNotNull($countRow);
        self::assertSame(
            1,
            (int) ($countRow['cnt'] ?? 0),
            'queuePendingIfAbsent must be idempotent — exactly one row after two execute() calls'
        );
    }

    /**
     * listAll() returns the new row with the recap_length alias present and
     * the recap body absent, with sim 9001 sorting first (ORDER BY sim DESC).
     *
     * Verification matrix row 37.
     */
    public function testListAllReturnsRowInViewerShape(): void
    {
        $step = new QueueSimSummaryStep($this->repo, $this->seasonQuery);
        $step->execute();

        $rows = $this->repo->listAll();

        self::assertNotEmpty($rows, 'listAll() must return at least one row');

        $firstRow = $rows[0];

        // sim 9001 must sort first — it exceeds every seed sim (≤ 689).
        self::assertSame(
            9001,
            $firstRow['sim'] ?? null,
            '9001 must be first in listAll() (ORDER BY sim DESC)'
        );

        // The recap_length computed alias must be present for the viewer index.
        self::assertArrayHasKey(
            'recap_length',
            $firstRow,
            'listAll() must expose the recap_length computed alias'
        );

        // The recap body must NOT be returned — listAll() is the index read.
        self::assertArrayNotHasKey(
            'recap_text',
            $firstRow,
            'listAll() must not return recap_text (index read, not body read)'
        );
    }
}
