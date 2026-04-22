<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use RatingsDiff\RatingsDiffRepository;

/**
 * Integration tests for RatingsDiffRepository against a real MariaDB instance.
 *
 * NOTE: This file lives in tests/DatabaseIntegration/ (NOT tests/RatingsDiff/)
 * to follow the project convention — real-DB tests are excluded from the default
 * PHPUnit suite and run explicitly via:
 *   vendor/bin/phpunit tests/DatabaseIntegration/RatingsDiffRepositoryTest.php
 *
 * The #[Group('database')] attribute on DatabaseTestCase prevents accidental
 * inclusion in the normal test run (which lacks DB_HOST / DB_USER / DB_PASS / DB_NAME).
 *
 * Each test wraps its work in a transaction that DatabaseTestCase rolls back in
 * tearDown(), so no data persists between tests.
 */
class RatingsDiffRepositoryTest extends DatabaseTestCase
{
    private RatingsDiffRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new RatingsDiffRepository($this->db);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Insert a snapshot row into ibl_plr_snapshots with all rating columns defaulted to 50.
     * Column names use the post-migration-113 names: r_drive_off, r_trans_off, r_tvr.
     */
    private function insertSnapshot(int $pid, int $seasonYear, string $phase = 'end-of-season'): void
    {
        $this->insertRow('ibl_plr_snapshots', [
            'pid'          => $pid,
            'name'         => 'Snapshot Player',
            'season_year'  => $seasonYear,
            'snapshot_phase' => $phase,
            'source_archive' => 'test.plr',
            'teamid'          => 1,
            'pos'          => 'PG',
            'oo'           => 50,
            'od'           => 50,
            'r_drive_off'  => 50,
            'dd'           => 50,
            'po'           => 50,
            'pd'           => 50,
            'r_trans_off'  => 50,
            'td'           => 50,
            'r_fga'        => 50,
            'r_fgp'        => 50,
            'r_fta'        => 50,
            'r_ftp'        => 50,
            'r_3ga'        => 50,
            'r_3gp'        => 50,
            'r_orb'        => 50,
            'r_drb'        => 50,
            'r_ast'        => 50,
            'r_stl'        => 50,
            'r_tvr'        => 50,
            'r_blk'        => 50,
            'r_foul'       => 50,
        ]);
    }

    // ---------------------------------------------------------------------------
    // getLatestEndOfSeasonYear()
    // ---------------------------------------------------------------------------

    public function test_it_returns_null_when_no_end_of_season_snapshots_exist(): void
    {
        // Ensure the ibl_plr_snapshots table has no end-of-season rows for this transaction
        $this->db->query("DELETE FROM ibl_plr_snapshots WHERE snapshot_phase = 'end-of-season'");

        $result = $this->repo->getLatestEndOfSeasonYear();

        self::assertNull($result);
    }

    public function test_it_returns_max_season_year_for_end_of_season_snapshots(): void
    {
        // Use very high PIDs to avoid FK conflicts with real data
        $this->insertTestPlayer(200_000_001, 'Player One', ['teamid' => 1, 'retired' => 0]);
        $this->insertTestPlayer(200_000_002, 'Player Two', ['teamid' => 1, 'retired' => 0]);
        $this->insertTestPlayer(200_000_003, 'Player Three', ['teamid' => 1, 'retired' => 0]);

        $this->insertSnapshot(200_000_001, 2023);
        $this->insertSnapshot(200_000_002, 2025);  // highest end-of-season year
        $this->insertSnapshot(200_000_003, 2024);

        // Also insert a non-end-of-season snapshot with a higher year — should be ignored
        $this->insertRow('ibl_plr_snapshots', [
            'pid'            => 200_000_001,
            'name'           => 'Player One',
            'season_year'    => 2026,
            'snapshot_phase' => 'training-camp',
            'source_archive' => 'test.plr',
            'teamid'            => 1,
            'pos'            => 'PG',
        ]);

        $result = $this->repo->getLatestEndOfSeasonYear();

        self::assertSame(2025, $result);
    }

    // ---------------------------------------------------------------------------
    // getDiffRows()
    // ---------------------------------------------------------------------------

    public function test_it_returns_one_row_per_non_retired_player_with_baseline_joined(): void
    {
        // Player A: non-retired, has snapshot → returns with s_oo populated
        $this->insertTestPlayer(200_000_010, 'Player Alpha', ['teamid' => 1, 'retired' => 0, 'oo' => 70]);
        $this->insertSnapshot(200_000_010, 2025);

        // Player B: non-retired, no snapshot → returns with s_oo = null
        $this->insertTestPlayer(200_000_011, 'Player Beta', ['teamid' => 1, 'retired' => 0, 'oo' => 60]);

        // Player C: retired → excluded from results
        $this->insertTestPlayer(200_000_012, 'Player Charlie', ['teamid' => 1, 'retired' => 1, 'oo' => 80]);
        $this->insertSnapshot(200_000_012, 2025);

        $rows = $this->repo->getDiffRows(2025);

        // Only A and B are returned (not retired Player C)
        $pids = array_column($rows, 'pid');
        self::assertContains(200_000_010, $pids);
        self::assertContains(200_000_011, $pids);
        self::assertNotContains(200_000_012, $pids);

        // Player A has snapshot data
        $rowA = null;
        $rowB = null;
        foreach ($rows as $row) {
            if ($row['pid'] === 200_000_010) {
                $rowA = $row;
            }
            if ($row['pid'] === 200_000_011) {
                $rowB = $row;
            }
        }

        self::assertNotNull($rowA, 'Player Alpha row not found');
        self::assertNotNull($rowB, 'Player Beta row not found');

        // A has a real snapshot join → s_oo is non-null
        self::assertNotNull($rowA['s_oo'], 'Player Alpha should have s_oo from snapshot');
        self::assertSame(50, $rowA['s_oo']); // we inserted 50 for oo in insertSnapshot()

        // B has no snapshot → s_oo is null (LEFT JOIN miss)
        self::assertNull($rowB['s_oo'], 'Player Beta should have null s_oo (no snapshot)');
    }

    public function test_it_applies_filter_tid_when_set(): void
    {
        // Insert players on two different teams
        $this->insertTestPlayer(200_000_020, 'Player On Team 1', ['teamid' => 1, 'retired' => 0]);
        $this->insertTestPlayer(200_000_021, 'Player On Team 2', ['teamid' => 2, 'retired' => 0]);
        $this->insertSnapshot(200_000_020, 2025);
        $this->insertSnapshot(200_000_021, 2025);

        $rows = $this->repo->getDiffRows(2025, 1);

        $pids = array_column($rows, 'pid');
        self::assertContains(200_000_020, $pids, 'teamid=1 player should be included');
        self::assertNotContains(200_000_021, $pids, 'teamid=2 player should be excluded');
    }
}
