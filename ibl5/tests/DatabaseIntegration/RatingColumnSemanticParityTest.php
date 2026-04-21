<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

/**
 * Verifies that the player-rating columns renamed by migration 113 stay
 * semantically consistent across the live, snapshot, and hist layers.
 *
 * History: before migration 113, `r_to` meant turnover rating in
 * ibl_plr/ibl_plr_snapshots but transition-offense rating in ibl_hist.
 * RefreshIblHistStep silently re-aliased the columns (snap.r_to → hist.r_tvr
 * and snap.`to` → hist.r_to), inverting semantics. A query that read `r_to`
 * from the wrong layer got the wrong stat.
 *
 * Migration 113 renamed the columns so `r_tvr` uniformly means turnover rating
 * and `r_trans_off` uniformly means transition-offense rating across every
 * layer. This test runs in CI forever to prevent future renames from
 * re-introducing that kind of silent flip.
 */
final class RatingColumnSemanticParityTest extends DatabaseTestCase
{
    public function testSnapshotAndHistRatingsMatchForSameSeason(): void
    {
        /** @var list<array{pid: int, year: int, snap_r_trans_off: int, hist_r_trans_off: int, snap_r_drive_off: int, hist_r_drive_off: int, snap_r_tvr: int, hist_r_tvr: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT snap.pid,
                    snap.season_year AS year,
                    snap.r_trans_off AS snap_r_trans_off,
                    h.r_trans_off    AS hist_r_trans_off,
                    snap.r_drive_off AS snap_r_drive_off,
                    h.r_drive_off    AS hist_r_drive_off,
                    snap.r_tvr       AS snap_r_tvr,
                    h.r_tvr          AS hist_r_tvr
             FROM ibl_plr_snapshots snap
             INNER JOIN ibl_hist h
                 ON h.pid  = snap.pid
                AND h.year = snap.season_year
             WHERE snap.stats_gm > 0
             ORDER BY snap.pid, snap.season_year
             LIMIT 50"
        );

        self::assertNotEmpty(
            $rows,
            'No snapshot-hist overlap found; CI seed may be empty. This test needs real data to prove parity.',
        );

        foreach ($rows as $row) {
            $context = sprintf('(pid=%d, year=%d)', $row['pid'], $row['year']);
            self::assertSame(
                $row['snap_r_trans_off'],
                $row['hist_r_trans_off'],
                "r_trans_off must match between snapshots and hist for {$context}. "
                . 'A mismatch means the semantic-flip has re-appeared.',
            );
            self::assertSame(
                $row['snap_r_drive_off'],
                $row['hist_r_drive_off'],
                "r_drive_off must match between snapshots and hist for {$context}.",
            );
            self::assertSame(
                $row['snap_r_tvr'],
                $row['hist_r_tvr'],
                "r_tvr must match between snapshots and hist for {$context}. "
                . 'A mismatch means r_tvr was re-aliased to a different column.',
            );
        }
    }

    public function testTurnoverRatingDiffersFromTransitionOffenseForAtLeastOneTuple(): void
    {
        // Guards against an accidental alias-collapse: if a future rewrite wired
        // both r_tvr and r_trans_off to the same source column, every row would
        // have equal values. Require at least one real-data tuple where they
        // differ.
        /** @var list<array{pid: int, year: int, r_tvr: int, r_trans_off: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT pid, year, r_tvr, r_trans_off
             FROM ibl_hist
             WHERE r_tvr != r_trans_off
             LIMIT 1"
        );

        self::assertNotEmpty(
            $rows,
            'Every ibl_hist row has r_tvr == r_trans_off. This almost certainly '
            . 'means the two columns are wired to the same source — they should '
            . 'represent independent ratings (turnover rating vs transition-offense rating).',
        );
    }

    public function testOlympicsHistUsesRenamedColumns(): void
    {
        // Smoke-check that the olympics hist rename also applied. Pulls one row
        // that touches all three renamed columns; any missing column would
        // throw during the SELECT, failing the test loudly.
        $this->fetchAll(
            "SELECT r_trans_off, r_drive_off, r_tvr FROM ibl_olympics_hist LIMIT 1"
        );
        self::assertTrue(true, 'ibl_olympics_hist has all three renamed columns.');
    }

    public function testSimDatesUsesSnakeCaseColumns(): void
    {
        // Migration 113 renamed `Start Date` / `End Date` → start_date / end_date.
        // A column-missing regression would throw during the SELECT.
        $this->fetchAll(
            "SELECT start_date, end_date FROM ibl_sim_dates LIMIT 1"
        );
        self::assertTrue(true, 'ibl_sim_dates has snake_case start_date/end_date columns.');
    }

    /**
     * @template T of array<string, mixed>
     * @param string $sql
     * @return list<T>
     */
    private function fetchAll(string $sql): array
    {
        $result = $this->db->query($sql);
        if ($result === false || $result === true) {
            self::fail('Query failed: ' . $this->db->error . ' — ' . $sql);
        }
        /** @var list<T> $rows */
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        return $rows;
    }
}
