<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Migration\MigrationFileResolver;
use Migration\MigrationRepository;
use Migration\MigrationRunner;
use PHPUnit\Framework\Attributes\Group;

/**
 * Real-database tests for the down-SQL machinery: the up-then-down round trip of
 * every shipped `.down.sql` pair, the MigrationRepository down-SQL methods, and
 * MigrationRunner::undoVanished() reading stored down SQL.
 *
 * DatabaseTestCase rolls every test back, so the only DDL used here is
 * CREATE/DROP TEMPORARY TABLE, which does not commit. undoMigration() uses
 * transactional(), which nests as a SAVEPOINT inside the test transaction.
 */
#[Group('database')]
final class MigrationDownSqlIntegrationTest extends DatabaseTestCase
{
    private const BATCH = 77;
    private const UP_SQL = "INSERT IGNORE INTO zz_pair_probe_backup SELECT * FROM zz_pair_probe WHERE v = 'old'; DELETE FROM zz_pair_probe WHERE v = 'old';";
    private const GOOD_DOWN_SQL = 'INSERT IGNORE INTO zz_pair_probe SELECT * FROM zz_pair_probe_backup;';
    private const BAD_DOWN_SQL = 'INSERT INTO zz_pair_probe (v) SELECT v FROM zz_pair_probe_backup;';

    private MigrationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->query(
            'CREATE TEMPORARY TABLE zz_pair_probe (id INT AUTO_INCREMENT PRIMARY KEY, v VARCHAR(20) NOT NULL) ENGINE=InnoDB'
        );
        $this->db->query('CREATE TEMPORARY TABLE zz_pair_probe_backup LIKE zz_pair_probe');
        $this->db->query("INSERT INTO zz_pair_probe (id, v) VALUES (1, 'keep'), (2, 'old')");

        $this->repository = new MigrationRepository($this->db);
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->query('DROP TEMPORARY TABLE IF EXISTS zz_pair_probe_backup');
            $this->db->query('DROP TEMPORARY TABLE IF EXISTS zz_pair_probe');
        }

        parent::tearDown();
    }

    // ── Round-trip helper fixtures ─────────────────────────────────────────────

    public function testRoundTripHelperPassesForIdPreservingDown(): void
    {
        $mismatches = $this->roundTripMismatches(self::UP_SQL, self::GOOD_DOWN_SQL, ['zz_pair_probe']);

        self::assertSame([], $mismatches);
    }

    public function testRoundTripHelperCatchesDownThatReassignsIds(): void
    {
        $mismatches = $this->roundTripMismatches(self::UP_SQL, self::BAD_DOWN_SQL, ['zz_pair_probe']);

        self::assertSame(['zz_pair_probe'], $mismatches);
    }

    // ── Every shipped pair ─────────────────────────────────────────────────────

    public function testEveryPairedMigrationRoundTrips(): void
    {
        $downFiles = glob(dirname(__DIR__, 2) . '/migrations/*.down.sql');
        self::assertIsArray($downFiles);

        $failures = [];
        foreach ($downFiles as $downPath) {
            $downName = basename($downPath);
            $upPath = substr($downPath, 0, -strlen('.down.sql')) . '.sql';

            $downSql = file_get_contents($downPath); // phpunit-hygiene-allow: migration SQL has no executable seam other than its text
            if ($downSql === false) {
                $failures[] = $downName . ': unreadable down file';
                continue;
            }

            if (!is_file($upPath)) {
                $failures[] = $downName . ': no partner up file';
                continue;
            }
            $upSql = file_get_contents($upPath); // phpunit-hygiene-allow: migration SQL has no executable seam other than its text
            if ($upSql === false) {
                $failures[] = $downName . ': unreadable up file';
                continue;
            }

            if (preg_match('/^--\s*restores:\s*(.+)$/mi', $downSql, $match) !== 1) {
                $failures[] = $downName . ': missing "-- restores:" line';
                continue;
            }

            $tables = [];
            foreach (explode(',', $match[1]) as $table) {
                $table = trim($table);
                if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
                    $failures[] = $downName . ': bad table name "' . $table . '"';
                    continue 2;
                }
                $tables[] = $table;
            }

            foreach ($this->roundTripMismatches($upSql, $downSql, $tables) as $table) {
                $failures[] = $downName . ': ' . $table;
            }
        }

        self::assertSame([], $failures);
    }

    // ── MigrationRepository ────────────────────────────────────────────────────

    public function testRecordMigrationWithDownRoundTripsText(): void
    {
        $downSql = "-- it's a comment\nDELETE FROM zz_pair_probe WHERE v = 'old';\n";

        $this->repository->recordMigrationWithDown('zz_probe_text.sql', self::BATCH, $downSql, hash('sha256', 'up'));

        $id = $this->migrationId('zz_probe_text.sql');
        self::assertSame($downSql, $this->repository->getStoredDownSql($id));
    }

    public function testGetAppliedDownStatesReportsHasDownPerRow(): void
    {
        $digest = hash('sha256', 'with-down');
        $this->repository->recordMigrationWithDown('zz_probe_with_down.sql', self::BATCH, self::GOOD_DOWN_SQL, $digest);
        $this->repository->recordMigration('zz_probe_without_down.sql', self::BATCH);

        $byName = [];
        foreach ($this->repository->getAppliedDownStates() as $state) {
            $byName[$state['migration']] = $state;
        }

        self::assertArrayHasKey('zz_probe_with_down.sql', $byName);
        self::assertArrayHasKey('zz_probe_without_down.sql', $byName);
        self::assertTrue($byName['zz_probe_with_down.sql']['hasDown']);
        self::assertSame($digest, $byName['zz_probe_with_down.sql']['upSha256']);
        self::assertFalse($byName['zz_probe_without_down.sql']['hasDown']);
        self::assertNull($byName['zz_probe_without_down.sql']['upSha256']);
    }

    public function testUndoMigrationRunsDownSqlAndDeletesRow(): void
    {
        $this->applyFixtureUp();
        self::assertSame([], $this->rowsWhere('zz_pair_probe', 'id = 2'));
        $this->repository->recordMigrationWithDown('zz_probe_undo.sql', self::BATCH, self::GOOD_DOWN_SQL, hash('sha256', 'x'));
        $id = $this->migrationId('zz_probe_undo.sql');

        $this->repository->undoMigration($id, self::GOOD_DOWN_SQL);

        self::assertSame([['id' => 2, 'v' => 'old']], $this->rowsWhere('zz_pair_probe', 'id = 2'));
        self::assertNull($this->repository->getStoredDownSql($id));
        self::assertSame([], $this->rowsWhere('migrations', "migration = 'zz_probe_undo.sql'"));
    }

    public function testUndoMigrationRollsBackWhenAStatementFails(): void
    {
        $this->applyFixtureUp();
        $this->repository->recordMigrationWithDown('zz_probe_fail.sql', self::BATCH, self::GOOD_DOWN_SQL, hash('sha256', 'x'));
        $id = $this->migrationId('zz_probe_fail.sql');
        $failingDown = 'INSERT IGNORE INTO zz_pair_probe SELECT * FROM zz_pair_probe_backup; INSERT INTO zz_no_such_table VALUES (1);';

        $thrown = false;
        try {
            $this->repository->undoMigration($id, $failingDown);
        } catch (\Throwable) {
            $thrown = true;
        }

        self::assertTrue($thrown, 'undoMigration must throw when a down statement fails');
        self::assertSame([], $this->rowsWhere('zz_pair_probe', 'id = 2'));
        self::assertCount(1, $this->rowsWhere('migrations', "migration = 'zz_probe_fail.sql'"));
    }

    // ── MigrationRunner::undoVanished() ────────────────────────────────────────

    public function testRunnerUndoesVanishedMigrationFromStoredSql(): void
    {
        $dir = sys_get_temp_dir() . '/ibl5_down_it_' . uniqid();
        mkdir($dir);

        try {
            $runner = new MigrationRunner($this->repository, new MigrationFileResolver($dir));
            $this->applyFixtureUp();
            $this->repository->recordMigrationWithDown('zz_probe_vanished.sql', self::BATCH, self::GOOD_DOWN_SQL, hash('sha256', 'x'));

            $undone = $runner->undoVanished();

            self::assertSame(['zz_probe_vanished.sql'], $undone);
            self::assertSame([['id' => 2, 'v' => 'old']], $this->rowsWhere('zz_pair_probe', 'id = 2'));
            self::assertSame([], $this->rowsWhere('migrations', "migration = 'zz_probe_vanished.sql'"));
        } finally {
            rmdir($dir);
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Run down, record the tables (state D), run up, run down, and return the
     * tables whose rows differ from D. Row ids are part of the comparison.
     *
     * @param list<string> $tables
     * @return list<string>
     */
    private function roundTripMismatches(string $upSql, string $downSql, array $tables): array
    {
        $this->repository->executeRawSql($downSql);

        $before = [];
        foreach ($tables as $table) {
            $before[$table] = $this->tableRows($table);
        }

        $this->runUp($upSql);
        $this->repository->executeRawSql($downSql);

        $mismatches = [];
        foreach ($tables as $table) {
            if ($this->tableRows($table) !== $before[$table]) {
                $mismatches[] = $table;
            }
        }

        return $mismatches;
    }

    /**
     * Run up text the way AswStaleRosterMigrationTest does: strip `--` comments,
     * split on `;`, and skip CREATE TABLE statements (DDL would commit).
     */
    private function runUp(string $upSql): void
    {
        $upSql = (string) preg_replace('/--[^\n]*/', '', $upSql);

        foreach (explode(';', $upSql) as $statement) {
            $statement = trim($statement);
            if ($statement === '' || stripos($statement, 'CREATE TABLE') === 0) {
                continue;
            }
            $this->db->query($statement);
        }
    }

    private function applyFixtureUp(): void
    {
        $this->runUp(self::UP_SQL);
    }

    /**
     * @return list<string> JSON-encoded rows, sorted
     */
    private function tableRows(string $table): array
    {
        $result = $this->db->query('SELECT * FROM ' . $table);
        self::assertInstanceOf(\mysqli_result::class, $result);

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = json_encode($row, JSON_THROW_ON_ERROR);
        }
        $result->free();

        sort($rows);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rowsWhere(string $table, string $where): array
    {
        $result = $this->db->query('SELECT * FROM ' . $table . ' WHERE ' . $where);
        self::assertInstanceOf(\mysqli_result::class, $result);

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();

        return $rows;
    }

    private function migrationId(string $name): int
    {
        $stmt = $this->db->prepare('SELECT id FROM migrations WHERE migration = ?');
        self::assertInstanceOf(\mysqli_stmt::class, $stmt);
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $stmt->close();

        self::assertIsArray($row);
        $id = $row['id'];
        self::assertIsInt($id);

        return $id;
    }
}
