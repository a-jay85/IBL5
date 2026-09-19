<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\UpdateAllTheThings;

use PHPUnit\Framework\Attributes\Group;
use PlrParser\PlrParserRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Updater\Steps\RefreshIblHistStep;

/**
 * Database coverage for the promote-prior-season-snapshot feature.
 *
 * The first five methods are Phase 1 characterization tests: they assert the
 * pre-implementation state, pass on master, and act as the baseline. Everything
 * below them is post-implementation — the Phase 5 promotion and idempotency
 * tests, and the Phase 6 ranking fixtures that run RefreshIblHistStep's own
 * SELECT_SQL against seeded rows.
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

    // ── Integration tests for promotePriorSeasonSnapshots() ───────────────

    public function testPromotesMidSeasonRowsToEndOfSeason(): void
    {
        $this->seedSnapshot(202000001, 2008, 'mid-season');
        $this->seedSnapshot(202000002, 2008, 'mid-season');
        $this->seedSnapshot(202000003, 2008, 'mid-season');

        $promoted = (new PlrParserRepository($this->db))->promotePriorSeasonSnapshots(2008);

        self::assertSame(3, $promoted);
        self::assertSame(3, $this->countSnapshotRows(2008, 'end-of-season'));
        self::assertSame(3, $this->countSnapshotRows(2008, 'mid-season'));
    }

    public function testIsIdempotentAcrossRepeatedRuns(): void
    {
        $this->seedSnapshot(202000004, 2008, 'mid-season');
        $this->seedSnapshot(202000005, 2008, 'mid-season');
        $this->seedSnapshot(202000006, 2008, 'mid-season');

        $repo = new PlrParserRepository($this->db);
        $first  = $repo->promotePriorSeasonSnapshots(2008);
        $second = $repo->promotePriorSeasonSnapshots(2008);

        self::assertSame(3, $first);
        self::assertSame(0, $second);
        self::assertSame(3, $this->countSnapshotRows(2008, 'end-of-season'));
    }

    public function testNeverOverwritesAnExistingEndOfSeasonRow(): void
    {
        $this->seedSnapshot(202000007, 2008, 'end-of-season', ['ordinal' => 999]);
        $this->seedSnapshot(202000007, 2008, 'mid-season');

        $promoted = (new PlrParserRepository($this->db))->promotePriorSeasonSnapshots(2008);

        self::assertSame(0, $promoted);

        $stmt = $this->db->prepare(
            'SELECT ordinal FROM ibl_plr_snapshots WHERE pid = ? AND season_year = ? AND snapshot_phase = ?'
        );
        self::assertNotFalse($stmt);
        $pid   = 202000007;
        $year  = 2008;
        $phase = 'end-of-season';
        $stmt->bind_param('iis', $pid, $year, $phase);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(999, (int) $row['ordinal']);
    }

    public function testCopiesCreatedAtFromTheSourceRow(): void
    {
        $this->seedSnapshot(202000008, 2008, 'mid-season', ['created_at' => '2026-01-15 10:00:00', 'phantom_games' => 7]);

        (new PlrParserRepository($this->db))->promotePriorSeasonSnapshots(2008);

        $stmt = $this->db->prepare(
            'SELECT created_at, phantom_games FROM ibl_plr_snapshots WHERE pid = ? AND season_year = ? AND snapshot_phase = ?'
        );
        self::assertNotFalse($stmt);
        $pid   = 202000008;
        $year  = 2008;
        $phase = 'end-of-season';
        $stmt->bind_param('iis', $pid, $year, $phase);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame('2026-01-15 10:00:00', $row['created_at']);
        self::assertSame(7, (int) $row['phantom_games']);
    }

    public function testDoesNotTouchOtherSeasons(): void
    {
        $this->seedSnapshot(202000009, 2007, 'mid-season');
        $this->seedSnapshot(202000010, 2008, 'mid-season');
        $this->seedSnapshot(202000011, 2009, 'mid-season');

        (new PlrParserRepository($this->db))->promotePriorSeasonSnapshots(2008);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS cnt FROM ibl_plr_snapshots WHERE snapshot_phase = 'end-of-season' AND season_year != ?"
        );
        self::assertNotFalse($stmt);
        $year = 2008;
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(0, (int) $row['cnt']);
    }

    public function testPromotedRowWinsTheIblHistRanking(): void
    {
        $this->seedSnapshot(202000012, 2008, 'mid-season', ['stats_gm' => 82, 'ordinal' => 50]);

        (new PlrParserRepository($this->db))->promotePriorSeasonSnapshots(2008);

        $stmt = $this->db->prepare(
            "SELECT snap.snapshot_phase FROM (
                SELECT s.*,
                    ROW_NUMBER() OVER (
                        PARTITION BY s.pid, s.season_year
                        ORDER BY
                            s.stats_gm DESC,
                            CASE s.snapshot_phase
                                WHEN 'end-of-season'       THEN  1
                                WHEN 'finals'              THEN  2
                                WHEN 'post-heat'           THEN  3
                                WHEN 'heat-finals'         THEN  4
                                WHEN 'heat-end'            THEN  5
                                WHEN 'playoffs-rd2-gm4-7'  THEN  6
                                WHEN 'playoffs-rd2-gm1-3'  THEN  7
                                WHEN 'playoffs-rd1-gm4-7'  THEN  8
                                WHEN 'playoffs-rd1-gm1-3'  THEN  9
                                WHEN 'conf-finals-gm4-7'   THEN 10
                                WHEN 'conf-finals-gm1-3'   THEN 11
                                WHEN 'heat-wb'             THEN 12
                                WHEN 'heat-lb'             THEN 13
                                ELSE 99
                            END ASC,
                            s.id DESC
                    ) AS rn
                FROM ibl_plr_snapshots s
                WHERE s.stats_gm > 0
                  AND s.pid = ?
                  AND s.season_year = ?
            ) snap
            WHERE rn = 1"
        );
        self::assertNotFalse($stmt);
        $pid  = 202000012;
        $year = 2008;
        $stmt->bind_param('ii', $pid, $year);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame('end-of-season', $row['snapshot_phase']);
    }

    public function testEndOfSeasonWinsOverHigherIdMidSeasonOnEqualStatsGm(): void
    {
        // end-of-season inserted first -> lower auto-increment id than the mid-season row below.
        // id DESC alone would pick mid-season; the phase rank must break the tie instead.
        $this->seedSnapshot(202099901, 2008, 'end-of-season', ['stats_gm' => 82, 'stats_pts' => 111, 'phantom_games' => 5]);
        $this->seedSnapshot(202099901, 2008, 'mid-season',    ['stats_gm' => 82, 'stats_pts' => 222, 'phantom_games' => 0]);

        $row = $this->rankedIblHistRowFor(202099901);

        self::assertNotNull($row);
        self::assertSame(111, (int) $row['pts'], 'Phase rank must pick the end-of-season row despite its lower id.');
    }

    public function testHigherStatsGmMidSeasonWinsOverLowerStatsGmEndOfSeason(): void
    {
        $this->seedSnapshot(202099902, 2008, 'mid-season',    ['stats_gm' => 82, 'stats_pts' => 222, 'phantom_games' => 5]);
        $this->seedSnapshot(202099902, 2008, 'end-of-season', ['stats_gm' => 40, 'stats_pts' => 111, 'phantom_games' => 0]);

        $row = $this->rankedIblHistRowFor(202099902);

        self::assertNotNull($row);
        self::assertSame(222, (int) $row['pts'], 'stats_gm DESC must outrank the end-of-season phase bonus.');
    }

    /**
     * Run the production ranking query — RefreshIblHistStep::SELECT_SQL, the exact
     * string the step feeds into `INSERT INTO ibl_hist` — and return the single
     * winning row for one pid. Reading the constant (rather than restating the SQL)
     * is what binds these fixtures to production: a change to the phase-rank CASE
     * expression fails them.
     *
     * @return array<string, mixed>|null
     */
    private function rankedIblHistRowFor(int $pid): ?array
    {
        /** @var string $selectSql */
        $selectSql = (new \ReflectionClassConstant(RefreshIblHistStep::class, 'SELECT_SQL'))->getValue();

        $stmt = $this->db->prepare('SELECT q.* FROM (' . $selectSql . ') q WHERE q.pid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        self::assertNull($result->fetch_assoc(), 'Ranking must collapse to exactly one row per (pid, season_year).');
        $stmt->close();

        return $row;
    }

    // ── Helpers (integration tests) ───────────────────────────────────────

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedSnapshot(int $pid, int $year, string $phase, array $overrides = []): void
    {
        $this->insertRow('ibl_plr_snapshots', array_merge([
            'pid'            => $pid,
            'name'           => 'Test Player',
            'season_year'    => $year,
            'snapshot_phase' => $phase,
            'source_archive' => 'test',
            'pos'            => 'PG',
            'stats_gm'       => 1,
            'ordinal'        => 1,
        ], $overrides));
    }
}
