<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

/**
 * Structural checks on ibl_hist to guard against drift in the materialization
 * logic (RefreshIblHistStep). Runs every CI.
 *
 * After migration 113, ibl_hist is populated from ibl_plr_snapshots with a
 * ROW_NUMBER() dedupe keyed on (pid, season_year). Any future change to the
 * INSERT SELECT that changes this invariant — missed dedupe, wrong column
 * pairing, value-drift in the copy — will trip one of these assertions.
 *
 * Complements RatingColumnSemanticParityTest (which asserts semantic
 * consistency of individual ratings across layers).
 */
final class IblHistStructuralTest extends DatabaseTestCase
{
    public function testRowCountEqualsDistinctPidYearInSnapshots(): void
    {
        /** @var array{c: int}|null $histCount */
        $histCount = $this->fetchOne("SELECT COUNT(*) AS c FROM ibl_hist");
        self::assertNotNull($histCount);

        /** @var array{c: int}|null $snapDistinct */
        $snapDistinct = $this->fetchOne(
            "SELECT COUNT(DISTINCT pid, season_year) AS c
             FROM ibl_plr_snapshots
             WHERE stats_gm > 0"
        );
        self::assertNotNull($snapDistinct);

        self::assertSame(
            $snapDistinct['c'],
            $histCount['c'],
            'ibl_hist row count must equal distinct (pid, season_year) in '
            . 'ibl_plr_snapshots with stats_gm > 0. A mismatch suggests the '
            . 'materialization dedupe logic in RefreshIblHistStep has drifted.',
        );
    }

    public function testPrimaryKeyIsUniqueOnPidYear(): void
    {
        /** @var array{c: int}|null $dupes */
        $dupes = $this->fetchOne(
            "SELECT COUNT(*) AS c FROM (
                 SELECT pid, year FROM ibl_hist GROUP BY pid, year HAVING COUNT(*) > 1
             ) t"
        );
        self::assertNotNull($dupes);
        self::assertSame(0, $dupes['c'], 'ibl_hist must have unique (pid, year) tuples.');
    }

    public function testRatingRangesAreSensible(): void
    {
        // Ratings are stored as ints. The live table uses tinyint unsigned
        // (0–255) and ibl_hist stores int(11). Any rating outside [0, 99]
        // for transition/drive offense would suggest a type mismatch.
        /** @var array{min_trans: int, max_trans: int, min_drive: int, max_drive: int, min_tvr: int, max_tvr: int}|null $ranges */
        $ranges = $this->fetchOne(
            "SELECT MIN(r_trans_off) AS min_trans, MAX(r_trans_off) AS max_trans,
                    MIN(r_drive_off) AS min_drive, MAX(r_drive_off) AS max_drive,
                    MIN(r_tvr)       AS min_tvr,   MAX(r_tvr)       AS max_tvr
             FROM ibl_hist"
        );
        self::assertNotNull($ranges);

        foreach (['trans' => 'r_trans_off', 'drive' => 'r_drive_off', 'tvr' => 'r_tvr'] as $key => $col) {
            self::assertGreaterThanOrEqual(
                0,
                $ranges['min_' . $key],
                "{$col} has negative values in ibl_hist — unexpected.",
            );
            self::assertLessThanOrEqual(
                255,
                $ranges['max_' . $key],
                "{$col} exceeds 255 in ibl_hist — wider than the source tinyint/smallint range.",
            );
        }
    }

    /**
     * @param string $sql
     * @return array<string, mixed>|null
     */
    private function fetchOne(string $sql): ?array
    {
        $result = $this->db->query($sql);
        if ($result === false || $result === true) {
            self::fail('Query failed: ' . $this->db->error . ' — ' . $sql);
        }
        /** @var array<string, mixed>|null $row */
        $row = $result->fetch_assoc();
        $result->free();
        return $row;
    }
}
