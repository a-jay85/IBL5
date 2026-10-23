<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Runs the shipped box-score team-name backfill migration end to end against
 * synthetic pairs: the collision-safe write, every skip rule, the guards, the
 * backup, and the rerun no-op each fail when the matching statement is removed.
 *
 * The migration holds DDL, START TRANSACTION and COMMIT, which implicitly commit
 * DatabaseTestCase's outer transaction. Fixtures therefore persist, so every id
 * this test inserts is tracked and deleted in tearDown (also when a test fails),
 * together with its rows in the migration's backup table.
 */
#[Group('database')]
final class BoxScoreTeamNameBackfillMigrationTest extends DatabaseTestCase
{
    private const SEASON = 1950;
    private const VISITOR_TID = 31;
    private const HOME_TID = 32;
    private const VISITOR_NAME = 'TestVis';
    private const HOME_NAME = 'TestHome';
    private const STALE_NAME = 'Stale';
    private const BACKUP_TABLE = 'ibl_box_scores_teams_franchise_names_backup';

    /** @var list<int> */
    private array $boxIds = [];

    /** @var list<array{int, int}> */
    private array $franchisePairs = [];

    /** @var list<int> */
    private array $insertedTeamIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        // The migration's DDL and COMMIT end the outer transaction anyway; end it up front.
        $this->db->commit();
    }

    protected function tearDown(): void
    {
        $cleanupFailure = null;
        try {
            if (isset($this->db)) {
                $this->db->rollback();
                $this->cleanUpFixtures();
            }
        } catch (\Throwable $e) {
            $cleanupFailure = $e;
        }

        parent::tearDown();

        if ($cleanupFailure !== null) {
            throw $cleanupFailure;
        }
    }

    public function testBackfillsStaleVisitorRowFromRowOrder(): void
    {
        $this->seedStandardFranchises();
        $a = $this->box('1949-11-15', self::STALE_NAME);
        $b = $this->box('1949-11-15', self::HOME_NAME);
        $controlA = $this->box('1949-11-16', self::VISITOR_NAME);
        $controlB = $this->box('1949-11-16', self::HOME_NAME);

        self::assertSame(0, $this->runMigration($this->db));

        self::assertSame(self::VISITOR_NAME, $this->nameOf($a));
        self::assertSame(self::HOME_NAME, $this->nameOf($b));
        self::assertSame(self::VISITOR_NAME, $this->nameOf($controlA));
        self::assertSame(self::HOME_NAME, $this->nameOf($controlB));
        self::assertSame(self::STALE_NAME, $this->backupNameOf($a), 'backupNameOf(A) holds the pre-state name of the changed row');
        self::assertNull($this->backupNameOf($b), 'the unchanged sibling is not backed up');
    }

    public function testRepairsCollidingPairWhereSiblingHoldsVisitorName(): void
    {
        $this->seedStandardFranchises();
        $a = $this->box('1949-11-17', self::STALE_NAME);
        $b = $this->box('1949-11-17', self::VISITOR_NAME);

        self::assertSame(0, $this->runMigration($this->db));

        self::assertSame(self::VISITOR_NAME, $this->nameOf($a), 'bad_id holds its target_name');
        self::assertSame(self::HOME_NAME, $this->nameOf($b), 'sibling_id holds the other franchise name');
        self::assertSame(self::STALE_NAME, $this->backupNameOf($a));
        self::assertSame(self::VISITOR_NAME, $this->backupNameOf($b));
    }

    public function testSkipsSingleRowTriple(): void
    {
        $this->seedStandardFranchises();
        $a = $this->box('1949-11-18', self::STALE_NAME);

        self::assertSame(0, $this->runMigration($this->db));

        self::assertSame(self::STALE_NAME, $this->nameOf($a));
        self::assertNull($this->backupNameOf($a));
    }

    public function testSkipsPairWithoutFranchiseSeasonRow(): void
    {
        // Season 1951 has no franchise rows for these teams.
        $this->ensureTeam(self::VISITOR_TID);
        $this->ensureTeam(self::HOME_TID);
        $a = $this->box('1950-11-19', self::STALE_NAME);
        $b = $this->box('1950-11-19', 'Whatever');

        self::assertSame(0, $this->runMigration($this->db));

        self::assertSame(self::STALE_NAME, $this->nameOf($a));
        self::assertSame('Whatever', $this->nameOf($b));
        self::assertNull($this->backupNameOf($a));
    }

    public function testSkipsAllStarTeamIds(): void
    {
        $away = \League\League::ALL_STAR_AWAY_TEAMID;
        $home = \League\League::ALL_STAR_HOME_TEAMID;
        $this->franchise($away, 'AllAway');
        $this->franchise($home, 'AllHome');
        $a = $this->box('1949-11-20', self::STALE_NAME, $away, $home);
        $b = $this->box('1949-11-20', 'AllHome', $away, $home);

        self::assertSame(0, $this->runMigration($this->db));

        self::assertSame(self::STALE_NAME, $this->nameOf($a));
        self::assertSame('AllHome', $this->nameOf($b));
        self::assertNull($this->backupNameOf($a));
    }

    public function testLeavesValidSwappedPairUntouched(): void
    {
        $this->seedStandardFranchises();
        $a = $this->box('1949-11-21', self::HOME_NAME);
        $b = $this->box('1949-11-21', self::VISITOR_NAME);
        $before = $this->backupCount();

        self::assertSame(0, $this->runMigration($this->db));

        self::assertSame(self::HOME_NAME, $this->nameOf($a));
        self::assertSame(self::VISITOR_NAME, $this->nameOf($b));
        self::assertSame($before, $this->backupCount());
    }

    public function testSecondRunIsNoOp(): void
    {
        $this->seedStandardFranchises();
        $a = $this->box('1949-11-15', self::STALE_NAME);
        $b = $this->box('1949-11-15', self::HOME_NAME);

        self::assertSame(0, $this->runMigration($this->db));
        $namesAfterFirst = [$this->nameOf($a), $this->nameOf($b)];
        $backupAfterFirst = $this->backupCount();
        self::assertSame([self::VISITOR_NAME, self::HOME_NAME], $namesAfterFirst);

        $second = $this->openSecondConnection();
        try {
            self::assertSame(0, $this->runMigration($second));
        } finally {
            $second->close();
        }

        self::assertSame($namesAfterFirst, [$this->nameOf($a), $this->nameOf($b)]);
        self::assertSame($backupAfterFirst, $this->backupCount());
    }

    public function testAbortsWhenTargetNameExceedsColumn(): void
    {
        $this->franchise(self::VISITOR_TID, 'SeventeenCharName');
        $this->franchise(self::HOME_TID, self::HOME_NAME);
        $a = $this->box('1949-11-22', self::STALE_NAME);
        $this->box('1949-11-22', self::HOME_NAME);

        self::assertSame(1172, $this->runMigration($this->db));

        self::assertSame(self::STALE_NAME, $this->nameOf($a));
    }

    public function testAbortsOnLeftoverPlaceholderOutsideWorkTable(): void
    {
        $this->seedStandardFranchises();
        $a = $this->box('1949-11-15', self::STALE_NAME);
        $this->box('1949-11-15', self::HOME_NAME);
        $this->box('1949-11-23', '~1');

        self::assertSame(1172, $this->runMigration($this->db));

        self::assertSame(self::STALE_NAME, $this->nameOf($a));
    }

    public function testPlaceholderStepIsLoadBearing(): void
    {
        $this->seedStandardFranchises();
        $a = $this->box('1949-11-17', self::STALE_NAME);
        $this->box('1949-11-17', self::VISITOR_NAME);

        $all = $this->statements();
        $withoutPlaceholder = array_values(array_filter(
            $all,
            static fn (string $sql): bool => !(stripos($sql, 'UPDATE ') === 0 && str_contains($sql, "CONCAT('~'")),
        ));
        self::assertCount(count($all) - 1, $withoutPlaceholder, 'exactly one placeholder UPDATE must be removed');

        self::assertSame(1062, $this->runStatements($this->db, $withoutPlaceholder));

        self::assertSame(self::STALE_NAME, $this->nameOf($a));
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function migrationPath(): string
    {
        $matches = glob(dirname(__DIR__, 2) . '/migrations/*_backfill_box_score_team_names.sql');
        self::assertIsArray($matches);
        self::assertCount(1, $matches, 'expected exactly one backfill_box_score_team_names migration');
        return $matches[0];
    }

    /**
     * @return list<string>
     */
    private function statements(): array
    {
        $sql = file_get_contents($this->migrationPath()); // phpunit-hygiene-allow: migration SQL has no executable seam other than its text
        self::assertIsString($sql);
        $sql = (string) preg_replace('/--[^\n]*/', '', $sql);

        $statements = [];
        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $statements[] = $statement;
            }
        }
        self::assertNotSame([], $statements);
        return $statements;
    }

    /**
     * @return int 0 on success, otherwise the errno of the first failing statement
     */
    private function runMigration(\mysqli $conn): int
    {
        return $this->runStatements($conn, $this->statements());
    }

    /**
     * @param list<string> $statements
     * @return int 0 on success, otherwise the errno of the first failing statement
     */
    private function runStatements(\mysqli $conn, array $statements): int
    {
        // The work table is session-scoped; a rerun on the same connection would collide.
        $conn->query('DROP TEMPORARY TABLE IF EXISTS tmp_bst_targets');

        foreach ($statements as $sql) {
            try {
                $result = $conn->query($sql);
                $errno = $conn->errno;
            } catch (\mysqli_sql_exception $e) {
                $result = false;
                $errno = (int) $e->getCode();
            }
            if ($result === false || $errno !== 0) {
                try {
                    $conn->query('ROLLBACK');
                } catch (\mysqli_sql_exception) {
                    // Nothing open to roll back is fine; the failure code is already captured.
                }
                return $errno !== 0 ? $errno : 1;
            }
            if ($result instanceof \mysqli_result) {
                $result->free();
            }
        }
        return 0;
    }

    private function nameOf(int $id): string
    {
        $result = $this->db->query("SELECT name FROM ibl_box_scores_teams WHERE id = $id");
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $result->free();
        self::assertNotNull($row, "no box-score team row with id $id");
        return (string) $row['name'];
    }

    private function backupTableExists(): bool
    {
        $result = $this->db->query(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . self::BACKUP_TABLE . "'"
        );
        self::assertInstanceOf(\mysqli_result::class, $result);
        $exists = $result->num_rows > 0;
        $result->free();
        return $exists;
    }

    private function backupCount(): int
    {
        if (!$this->backupTableExists()) {
            return 0;
        }
        $result = $this->db->query('SELECT COUNT(*) AS c FROM ' . self::BACKUP_TABLE);
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $result->free();
        self::assertNotNull($row);
        return (int) $row['c'];
    }

    private function backupNameOf(int $id): ?string
    {
        if (!$this->backupTableExists()) {
            return null;
        }
        $result = $this->db->query('SELECT name FROM ' . self::BACKUP_TABLE . " WHERE id = $id");
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $result->free();
        return $row === null ? null : (string) $row['name'];
    }

    /**
     * Mirrors DatabaseTestCase::setUp: a TEMPORARY table is session-scoped, so the
     * rerun case needs a fresh session, as bin/db-migrate gives each run.
     */
    private function openSecondConnection(): \mysqli
    {
        $conn = new \mysqli();
        $conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        $conn->real_connect(
            (string) getenv('DB_HOST'),
            (string) getenv('DB_USER'),
            (string) getenv('DB_PASS'),
            (string) getenv('DB_NAME'),
        );
        if ($conn->connect_errno !== 0) {
            self::fail('Second database connection failed: ' . $conn->connect_error);
        }
        return $conn;
    }

    private function seedStandardFranchises(): void
    {
        $this->franchise(self::VISITOR_TID, self::VISITOR_NAME);
        $this->franchise(self::HOME_TID, self::HOME_NAME);
    }

    private function franchise(int $franchiseId, string $teamName): void
    {
        $this->ensureTeam($franchiseId);
        $this->insertFranchiseSeasonRow($franchiseId, self::SEASON, $teamName);
        $this->franchisePairs[] = [$franchiseId, self::SEASON];
    }

    private function box(
        string $date,
        string $name,
        int $visitorTid = self::VISITOR_TID,
        int $homeTid = self::HOME_TID,
    ): int {
        $this->ensureTeam($visitorTid);
        $this->ensureTeam($homeTid);
        $id = $this->insertTeamBoxscoreRow($date, $name, 1, $visitorTid, $homeTid);
        $this->boxIds[] = $id;
        return $id;
    }

    /**
     * ibl_box_scores_teams and ibl_franchise_seasons carry FKs to ibl_team_info.
     */
    private function ensureTeam(int $teamId): void
    {
        $result = $this->db->query("SELECT 1 FROM ibl_team_info WHERE teamid = $teamId");
        self::assertInstanceOf(\mysqli_result::class, $result);
        $exists = $result->num_rows > 0;
        $result->free();
        if ($exists) {
            return;
        }

        $this->insertRow('ibl_team_info', [
            'teamid' => $teamId,
            'team_city' => 'BST City',
            'team_name' => 'BST' . $teamId,
        ]);
        $this->insertedTeamIds[] = $teamId;
    }

    private function cleanUpFixtures(): void
    {
        if ($this->boxIds !== []) {
            $ids = implode(',', $this->boxIds);
            if ($this->backupTableExists()) {
                $this->db->query('DELETE FROM ' . self::BACKUP_TABLE . " WHERE id IN ($ids)");
            }
            $this->db->query("DELETE FROM ibl_box_scores_teams WHERE id IN ($ids)");
        }
        foreach ($this->franchisePairs as [$franchiseId, $endingYear]) {
            $this->db->query(
                "DELETE FROM ibl_franchise_seasons WHERE franchise_id = $franchiseId AND season_ending_year = $endingYear"
            );
        }
        if ($this->insertedTeamIds !== []) {
            $this->db->query('DELETE FROM ibl_team_info WHERE teamid IN (' . implode(',', $this->insertedTeamIds) . ')');
        }
    }
}
