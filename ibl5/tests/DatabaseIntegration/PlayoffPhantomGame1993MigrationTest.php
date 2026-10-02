<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Exercises the data half of migration 179 (mark the 1993 playoff phantom game)
 * against seeded snapshot rows. Each test seeds its own rows, applies the
 * shipped migration file's UPDATE statements, and rolls back.
 *
 * The file opens with `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`. DDL implicitly
 * commits in MariaDB, which would defeat the per-test transaction rollback. The
 * column already exists in the test schema, so only the UPDATE statements are
 * parsed out of the real file and executed. The file is still read from disk,
 * so a wrong pid list or season in the shipped SQL fails here. The expected pid
 * list is parsed from the same file rather than transcribed.
 */
#[Group('database')]
final class PlayoffPhantomGame1993MigrationTest extends DatabaseTestCase
{
    private const SEASON_YEAR = 1993;
    private const OTHER_SEASON_YEAR = 1992;
    private const UNLISTED_PID = 99999;
    private const EXPECTED_PID_COUNT = 24;

    public function testShippedFileListsExactly24DistinctPids(): void
    {
        $pids = $this->listedPids();

        self::assertCount(self::EXPECTED_PID_COUNT, $pids);
        self::assertCount(self::EXPECTED_PID_COUNT, array_unique($pids), 'pid list must not contain duplicates');
    }

    public function testMigrationMarksAllListedPidsForSeason1993(): void
    {
        $pids = $this->listedPids();
        foreach ($pids as $pid) {
            $this->seedSnapshot('ibl_plr_snapshots', $pid, self::SEASON_YEAR);
        }

        $this->applyUpdates();

        foreach ($pids as $pid) {
            self::assertSame(1, $this->phantomGames('ibl_plr_snapshots', $pid, self::SEASON_YEAR), "pid $pid must be marked");
        }
    }

    public function testMigrationLeavesUnlistedPidAndOtherSeasonsUntouched(): void
    {
        $listed = $this->listedPids()[0];
        $this->seedSnapshot('ibl_plr_snapshots', self::UNLISTED_PID, self::SEASON_YEAR);
        $this->seedSnapshot('ibl_plr_snapshots', $listed, self::OTHER_SEASON_YEAR);
        $this->seedSnapshot('ibl_plr_snapshots', $listed, self::SEASON_YEAR);

        $this->applyUpdates();

        self::assertSame(0, $this->phantomGames('ibl_plr_snapshots', self::UNLISTED_PID, self::SEASON_YEAR), 'unlisted pid must stay 0');
        self::assertSame(0, $this->phantomGames('ibl_plr_snapshots', $listed, self::OTHER_SEASON_YEAR), 'listed pid in another season must stay 0');
        self::assertSame(1, $this->phantomGames('ibl_plr_snapshots', $listed, self::SEASON_YEAR), 'control: listed pid in 1993 is marked');
    }

    public function testMigrationDoesNotUpdateOlympicsSnapshots(): void
    {
        $listed = $this->listedPids()[0];
        $this->seedSnapshot('ibl_olympics_plr_snapshots', $listed, self::SEASON_YEAR);

        $this->applyUpdates();

        self::assertSame(0, $this->phantomGames('ibl_olympics_plr_snapshots', $listed, self::SEASON_YEAR), 'migration only adds the olympics column; it marks no rows');
    }

    public function testMigrationIsIdempotent(): void
    {
        $listed = $this->listedPids()[0];
        $this->seedSnapshot('ibl_plr_snapshots', $listed, self::SEASON_YEAR);

        $this->applyUpdates();
        $this->applyUpdates();

        self::assertSame(1, $this->phantomGames('ibl_plr_snapshots', $listed, self::SEASON_YEAR), 'a second run must keep the flag at 1, not increment it');
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function readMigration(): string
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/migrations/179_mark_playoff_phantom_game_1993.sql');
        self::assertIsString($sql);
        return (string) preg_replace('/--[^\n]*/', '', $sql);
    }

    /**
     * @return list<string> the UPDATE statements from the shipped file
     */
    private function updateStatements(): array
    {
        $updates = [];
        foreach (explode(';', $this->readMigration()) as $statement) {
            $statement = trim($statement);
            if (stripos($statement, 'UPDATE ') === 0) {
                $updates[] = $statement;
            }
        }
        self::assertNotEmpty($updates, 'no UPDATE statements parsed from migration 179');
        return $updates;
    }

    /**
     * @return list<int>
     */
    private function listedPids(): array
    {
        $pids = [];
        foreach ($this->updateStatements() as $statement) {
            self::assertSame(1, preg_match('/pid\s+IN\s*\(([^)]*)\)/i', $statement, $m), 'UPDATE must carry a pid IN (...) list');
            foreach (explode(',', $m[1]) as $token) {
                $pids[] = (int) trim($token);
            }
        }
        return $pids;
    }

    private function applyUpdates(): void
    {
        foreach ($this->updateStatements() as $statement) {
            self::assertTrue($this->db->query($statement), $this->db->error);
        }
    }

    private function seedSnapshot(string $table, int $pid, int $seasonYear): void
    {
        $this->insertRow($table, [
            'pid' => $pid,
            'name' => "Phantom Test $pid",
            'season_year' => $seasonYear,
            'snapshot_phase' => 'end-of-season',
            'source_archive' => 'test-179',
        ]);
    }

    private function phantomGames(string $table, int $pid, int $seasonYear): int
    {
        $result = $this->db->query("SELECT po_phantom_games FROM $table WHERE pid = $pid AND season_year = $seasonYear");
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $result->free();
        self::assertNotNull($row, "no seeded row for pid $pid season $seasonYear in $table");
        return (int) $row['po_phantom_games'];
    }
}
