<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\UpdateAllTheThings;

use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * Characterization tests for the promote-prior-season-snapshot feature.
 *
 * All tests assert the CURRENT (pre-implementation) state — they pass on
 * master before any production code changes and act as a baseline for
 * Phase 6 implementation work.
 */
#[Group('database')]
class PromotePriorSeasonSnapshotTest extends DatabaseTestCase
{
    // ── Characterization test 1: snapshot gap ─────────────────────────────

    /**
     * Pins the broken baseline: a champion row in ibl_jsb_history and
     * mid-season snapshots do NOT produce end-of-season rows on their own.
     */
    public function testEndOfSeasonCountIsZeroWithOnlyMidSeasonRows(): void
    {
        $this->insertJsbHistoryChampion(2008);
        $this->insertSnapshotRow(201000001, 'Player One', 2008, 'mid-season');
        $this->insertSnapshotRow(201000002, 'Player Two', 2008, 'mid-season');

        self::assertSame(0, $this->countSnapshotRows(2008, 'end-of-season'));
    }

    // ── Characterization test 2: ranking baseline ─────────────────────────

    /**
     * Phase 1 baseline: a single mid-season row exists and no end-of-season
     * counterpart is present without the promotion step running.
     */
    public function testMidSeasonRowExistsAndEndOfSeasonDoesNot(): void
    {
        $this->insertSnapshotRow(201000003, 'Player Three', 2008, 'mid-season');

        self::assertSame(1, $this->countSnapshotRows(2008, 'mid-season'));
        self::assertSame(0, $this->countSnapshotRows(2008, 'end-of-season'));
    }

    // ── Boundary case 1: no champion ──────────────────────────────────────

    /**
     * No end-of-season rows are produced when there is no champion row,
     * even if mid-season snapshots are present.
     */
    public function testNoEndOfSeasonRowWhenNoChampion(): void
    {
        $this->insertSnapshotRow(201000004, 'Player Four', 2008, 'mid-season');
        $this->insertSnapshotRow(201000005, 'Player Five', 2008, 'mid-season');

        self::assertSame(0, $this->countSnapshotRows(2008, 'end-of-season'));
    }

    // ── Boundary case 2: end-of-season already present ────────────────────

    /**
     * An existing end-of-season row retains its ordinal and the count stays 1.
     */
    public function testEndOfSeasonAlreadyPresent(): void
    {
        $this->insertSnapshotRow(201000010, 'Player Ten', 2008, 'end-of-season', 999);
        $this->insertSnapshotRow(201000010, 'Player Ten', 2008, 'mid-season');

        self::assertSame(1, $this->countSnapshotRows(2008, 'end-of-season'));

        $stmt = $this->db->prepare(
            'SELECT ordinal FROM ibl_plr_snapshots WHERE pid = ? AND season_year = ? AND snapshot_phase = ?'
        );
        self::assertNotFalse($stmt);
        $pid    = 201000010;
        $year   = 2008;
        $phase  = 'end-of-season';
        $stmt->bind_param('iis', $pid, $year, $phase);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(999, (int) $row['ordinal']);
    }

    // ── Boundary case 3: no source rows ───────────────────────────────────

    /**
     * A champion row alone (no snapshot rows of any phase) yields zero
     * end-of-season rows.
     */
    public function testNoPromotionWithNoMidSeasonRows(): void
    {
        $this->insertJsbHistoryChampion(2008);

        self::assertSame(0, $this->countSnapshotRows(2008, 'end-of-season'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function insertSnapshotRow(
        int $pid,
        string $name,
        int $seasonYear,
        string $snapshotPhase,
        int $ordinal = 1,
    ): void {
        $this->insertRow('ibl_plr_snapshots', [
            'pid'            => $pid,
            'name'           => $name,
            'season_year'    => $seasonYear,
            'snapshot_phase' => $snapshotPhase,
            'source_archive' => 'test-archive.zip',
            'ordinal'        => $ordinal,
        ]);
    }

    private function insertJsbHistoryChampion(int $seasonYear): void
    {
        $this->insertRow('ibl_jsb_history', [
            'season_year'      => $seasonYear,
            'team_name'        => 'Test Champions',
            'won_championship' => 1,
        ]);
    }

    private function countSnapshotRows(int $seasonYear, string $snapshotPhase): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS cnt FROM ibl_plr_snapshots WHERE season_year = ? AND snapshot_phase = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('is', $seasonYear, $snapshotPhase);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertNotNull($row);

        return (int) $row['cnt'];
    }
}
