<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Runs the shipped stale-ASW-rows migration against seeded rows: the 2009 delete
 * with its backup, the 2008 participant backfill, the conference-family guard,
 * and the rerun no-op.
 *
 * CI applies every migration before the suite, so the backup tables already exist
 * (asserted first). The test executes only the non-DDL statements, which keeps the
 * run inside DatabaseTestCase's transaction and rolls every row back in tearDown.
 */
#[Group('database')]
final class AswStaleRosterMigrationTest extends DatabaseTestCase
{
    private const ROSTER_BACKUP = 'ibl_jsb_allstar_rosters_backup_210';
    private const SCORE_BACKUP = 'ibl_jsb_allstar_scores_backup_210';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertBackupTablesExist();
        $this->seedRows();
    }

    public function testMigrationDeletesOnly2009RowsAndBacksThemUp(): void
    {
        $before2009Rosters = $this->countRows('ibl_jsb_allstar_rosters', 'season_year = 2009');
        $before2009Scores = $this->countRows('ibl_jsb_allstar_scores', 'season_year = 2009');
        $before2007Rosters = $this->countRows('ibl_jsb_allstar_rosters', 'season_year = 2007');
        $before2007Scores = $this->countRows('ibl_jsb_allstar_scores', 'season_year = 2007');
        $before2008Rosters = $this->countRows('ibl_jsb_allstar_rosters', 'season_year = 2008');
        self::assertGreaterThan(0, $before2009Rosters);
        self::assertGreaterThan(0, $before2009Scores);

        $this->runMigration();

        self::assertSame(0, $this->countRows('ibl_jsb_allstar_rosters', 'season_year = 2009'));
        self::assertSame(0, $this->countRows('ibl_jsb_allstar_scores', 'season_year = 2009'));
        self::assertSame($before2007Rosters, $this->countRows('ibl_jsb_allstar_rosters', 'season_year = 2007'));
        self::assertSame($before2007Scores, $this->countRows('ibl_jsb_allstar_scores', 'season_year = 2007'));
        self::assertSame($before2008Rosters, $this->countRows('ibl_jsb_allstar_rosters', 'season_year = 2008'));
        self::assertGreaterThanOrEqual($before2009Rosters, $this->countRows(self::ROSTER_BACKUP, 'season_year = 2009'));
        self::assertGreaterThanOrEqual($before2009Scores, $this->countRows(self::SCORE_BACKUP, 'season_year = 2009'));
    }

    public function testMigrationBackfills2008ParticipantCounts(): void
    {
        $this->runMigration();

        self::assertSame(20, $this->awardCount('Rookie-Sophomore Challenge'));
        self::assertSame(8, $this->awardCount('Three-Point Contest'));
        self::assertSame(8, $this->awardCount('Slam Dunk Competition'));
    }

    public function testMigrationHonorsConferenceFamilyGuard(): void
    {
        $this->runMigration();

        $result = $this->db->query(
            "SELECT award FROM ibl_awards WHERE year = 2008 AND name = 'Chris Paul'"
            . " AND award IN ('Eastern Conference All-Star', 'Western Conference All-Star')"
        );
        self::assertInstanceOf(\mysqli_result::class, $result);
        $awards = [];
        while ($row = $result->fetch_assoc()) {
            $awards[] = (string) $row['award'];
        }
        $result->free();

        self::assertSame(['Western Conference All-Star'], $awards);
    }

    public function testMigrationSkipsBackfillWhen2008RostersAreMissing(): void
    {
        $this->db->query('DELETE FROM ibl_jsb_allstar_rosters WHERE season_year = 2008');
        $before = $this->countRows('ibl_awards', 'year = 2008');

        $this->runMigration();

        self::assertSame($before, $this->countRows('ibl_awards', 'year = 2008'));
    }

    public function testMigrationSecondRunInsertsNothing(): void
    {
        $this->runMigration();
        $afterFirst = $this->countRows('ibl_awards', 'year = 2008');
        self::assertGreaterThan(0, $afterFirst);

        $this->runMigration();

        self::assertSame($afterFirst, $this->countRows('ibl_awards', 'year = 2008'));
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function assertBackupTablesExist(): void
    {
        foreach ([self::ROSTER_BACKUP, self::SCORE_BACKUP] as $table) {
            $result = $this->db->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $table . "'"
            );
            self::assertInstanceOf(\mysqli_result::class, $result);
            $exists = $result->num_rows > 0;
            $result->free();
            self::assertTrue($exists, "Backup table $table must exist; apply migrations first (bin/db-migrate)");
        }
    }

    private function seedRows(): void
    {
        foreach ([2007, 2008, 2009] as $year) {
            $this->db->query(
                "INSERT IGNORE INTO ibl_jsb_allstar_rosters (season_year, event_type, roster_slot, pid, player_name)"
                . " VALUES ($year, 'allstar_1', 1, NULL, NULL)"
            );
        }
        foreach ([2007, 2009] as $year) {
            $this->db->query(
                "INSERT IGNORE INTO ibl_jsb_allstar_scores (season_year, contest_type, round, participant_slot, pid, score)"
                . " VALUES ($year, 'three_point', 1, 1, NULL, 10)"
            );
        }
        // CI already applied the migration, so clear its 2008 rows (rolled back in tearDown)
        // before seeding the one Western row the family guard must respect.
        $this->db->query('DELETE FROM ibl_awards WHERE year = 2008');
        $this->db->query(
            "INSERT INTO ibl_awards (year, award, name) VALUES (2008, 'Western Conference All-Star', 'Chris Paul')"
        );
    }

    /**
     * @return list<string>
     */
    private function statements(): array
    {
        $matches = glob(dirname(__DIR__, 2) . '/migrations/*_asw_stale_rows_allstar_awards_backfill.sql');
        self::assertIsArray($matches);
        self::assertCount(1, $matches, 'expected exactly one asw_stale_rows_allstar_awards_backfill migration');
        $sql = file_get_contents($matches[0]); // phpunit-hygiene-allow: migration SQL has no executable seam other than its text
        self::assertIsString($sql);
        $sql = (string) preg_replace('/--[^\n]*/', '', $sql);

        $statements = [];
        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement === '' || stripos($statement, 'CREATE TABLE') === 0) {
                continue;
            }
            $statements[] = $statement;
        }
        self::assertNotSame([], $statements);
        return $statements;
    }

    private function runMigration(): void
    {
        foreach ($this->statements() as $sql) {
            $this->db->query($sql);
        }
    }

    private function countRows(string $table, string $where): int
    {
        $result = $this->db->query("SELECT COUNT(*) AS c FROM $table WHERE $where");
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $result->free();
        self::assertNotNull($row);
        return (int) $row['c'];
    }

    private function awardCount(string $award): int
    {
        return $this->countRows('ibl_awards', "year = 2008 AND award = '" . $this->db->real_escape_string($award) . "'");
    }
}
