<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Boxscore\Phantom2007BoxscoreRepair;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for Phantom2007BoxscoreRepair.
 *
 * Transaction isolation note: Phantom2007BoxscoreRepair::runRepair() always calls
 * $this->db->begin_transaction() internally on the 'proceed' path, which in
 * MariaDB implicitly commits any already-open transaction. DatabaseTestCase's
 * outer T1 (opened by setUp()) is committed the moment runRepair() reaches that
 * call.
 *
 * Tests that only call assertPreconditions() — or that trigger a noop/throw path
 * before begin_transaction() fires — still benefit from T1's rollback in tearDown().
 *
 * Tests that call runRepair() on the 'proceed' path leave committed state in the
 * test database and must call cleanAll() before seeding so repeated runs within a
 * suite execution work correctly. tearDownAfterClass() removes everything this
 * class commits.
 *
 * Prerequisites: migration 183 (backup tables) must be applied before this suite
 * runs. bin/db-test-up delete-phantom-2007-preseason-boxscores applies it
 * automatically via the worktree's migrations/ directory.
 *
 * @see Phantom2007BoxscoreRepair The class under test
 */
#[Group('database')]
final class Phantom2007BoxscoreRepairTest extends DatabaseTestCase
{
    /** Sim ID for ibl_sim_game_recaps; FK-constrained to ibl_sim_summaries. */
    private const TEST_SIM = 2007;

    /** Base PID for all 14 test player rows (200002007–200002020). */
    private const PID_BASE = 200002007;

    /**
     * Removes all rows this class commits to the shared test database.
     *
     * Cleanup order: backup tables first, then live boxscore rows in the 2007
     * date ranges, then sim recap + summary rows, then ibl_plr rows.
     */
    public static function tearDownAfterClass(): void
    {
        $db = new \mysqli();
        $db->real_connect(
            (string) getenv('DB_HOST'),
            (string) getenv('DB_USER'),
            (string) getenv('DB_PASS'),
            (string) getenv('DB_NAME')
        );

        // 1. Backup tables.
        $db->query("DELETE FROM ibl_box_scores_teams_season2007_phantom_backup WHERE game_date BETWEEN '2007-09-01' AND '2007-10-31'");
        $db->query("DELETE FROM ibl_box_scores_season2007_phantom_backup WHERE game_date BETWEEN '2007-09-01' AND '2007-10-31'");

        // 2. Live boxscore tables at all seeded dates.
        foreach (['2007-09-10', '2007-10-20', '2007-10-15', '2007-10-05', '2007-11-10', '2007-12-20', '2007-12-05'] as $date) {
            $db->query("DELETE FROM ibl_box_scores WHERE game_date = '$date' AND visitor_teamid IN (22,24) AND home_teamid IN (22,24)");
            $db->query("DELETE FROM ibl_box_scores_teams WHERE game_date = '$date' AND visitor_teamid IN (22,24) AND home_teamid IN (22,24)");
        }

        // 3. Sim recap rows must be deleted before sim summaries (FK).
        $db->query('DELETE FROM ibl_sim_game_recaps WHERE sim = ' . self::TEST_SIM);
        $db->query('DELETE FROM ibl_sim_summaries WHERE sim = ' . self::TEST_SIM);

        // 4. ibl_plr rows — boxscore FK cleared above, so these are safe now.
        $pid_end = self::PID_BASE + 13;
        $db->query("DELETE FROM ibl_plr WHERE pid BETWEEN " . self::PID_BASE . " AND $pid_end AND uuid LIKE 'test-2007-%'");

        $db->close();

        parent::tearDownAfterClass();
    }

    // ─────────────────────────────────────────── helpers ──────────────────────

    /** @param array{phantom_team_rows: int, phantom_player_rows: int, real_oct_team_rows: int}|null $expectedOverride */
    private function makeRepair(?array $expectedOverride): Phantom2007BoxscoreRepair
    {
        return new Phantom2007BoxscoreRepair($this->db, $expectedOverride);
    }

    /**
     * Deletes all rows seeded by this class from both live tables, both backup
     * tables, and ibl_sim_game_recaps.
     *
     * In runRepair tests, call this before seeding; the deletes are inside T1,
     * which begin_transaction() in runRepair commits automatically before T2 runs.
     */
    private function cleanAll(): void
    {
        // Backup tables first.
        $this->db->query("DELETE FROM ibl_box_scores_teams_season2007_phantom_backup WHERE game_date BETWEEN '2007-09-01' AND '2007-10-31'");
        $this->db->query("DELETE FROM ibl_box_scores_season2007_phantom_backup WHERE game_date BETWEEN '2007-09-01' AND '2007-10-31'");

        // Live rows at all seeded dates.
        foreach (['2007-09-10', '2007-10-20', '2007-10-15', '2007-10-05', '2007-11-10', '2007-12-20', '2007-12-05'] as $date) {
            $this->db->query("DELETE FROM ibl_box_scores WHERE game_date = '$date' AND visitor_teamid IN (22,24) AND home_teamid IN (22,24)");
            $this->db->query("DELETE FROM ibl_box_scores_teams WHERE game_date = '$date' AND visitor_teamid IN (22,24) AND home_teamid IN (22,24)");
        }

        // Sim recap rows.
        $this->db->query('DELETE FROM ibl_sim_game_recaps WHERE sim = ' . self::TEST_SIM);
    }

    /**
     * INSERT IGNORE a single player into ibl_plr with a test-2007-prefixed uuid.
     *
     * INSERT IGNORE is safe to call repeatedly: if the pid already exists (from a
     * previous committed runRepair test), it is a no-op.
     */
    private function insertPidIgnore(int $pid, string $name, int $teamid = 1): void
    {
        $uuid = sprintf('test-2007-%d', $pid);
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO ibl_plr
             (pid, name, age, teamid, pos, stamina, exp, bird, cy, cyt, salary_yr1, salary_yr2, retired, ordinal, droptime, uuid)
             VALUES (?, ?, 27, ?, \'PG\', 80, 5, 3, 1, 3, 1500, 1600, 0, 1, 0, ?)'
        );
        self::assertNotFalse($stmt, 'Prepare insertPidIgnore failed: ' . $this->db->error);
        $stmt->bind_param('isis', $pid, $name, $teamid, $uuid);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Seeds all 7 games (14 team rows + 14 player rows).
     *
     * Games:
     *   G1  2007-11-10 24@22  real Nov; must survive
     *   G1p 2007-09-10 24@22  Sep phantom of G1; deleted
     *   G2  2007-12-20 22@24  real Dec; must survive
     *   G2p 2007-10-20 22@24  Oct phantom of G2 (same scores); deleted
     *   G3  2007-10-15 24@22  real Oct HEAT; must survive
     *   G4  2007-12-05 24@22  real Dec; must survive
     *   G4n 2007-10-05 24@22  Oct whose Dec twin (G4) has different scores; must survive
     *
     * Returns the expected-override array for Phantom2007BoxscoreRepair.
     *
     * @return array{phantom_team_rows: int, phantom_player_rows: int, real_oct_team_rows: int}
     */
    private function seedAll(): array
    {
        // Seed all 14 PIDs into ibl_plr (FK requirement for ibl_box_scores).
        for ($i = 0; $i < 14; $i++) {
            $pid    = self::PID_BASE + $i;
            $teamid = ($i % 2 === 0) ? 24 : 22;
            $this->insertPidIgnore($pid, 'P2007-' . $i, $teamid);
        }

        $pid = self::PID_BASE;

        // G1: 2007-11-10 visitor=24 home=22
        $this->insertTeamBoxscoreRow('2007-11-10', 'TeamV', 1, 24, 22);
        $this->insertTeamBoxscoreRow('2007-11-10', 'TeamH', 1, 24, 22);
        $this->insertPlayerBoxscoreRow('2007-11-10', $pid++, 'P2007-0', 'PG', 24, 22, 24, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);
        $this->insertPlayerBoxscoreRow('2007-11-10', $pid++, 'P2007-1', 'PG', 24, 22, 22, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);

        // G1p: 2007-09-10 visitor=24 home=22 (Sep phantom; same teams as G1 but Sep date = no Nov twin needed)
        $this->insertTeamBoxscoreRow('2007-09-10', 'TeamV', 1, 24, 22);
        $this->insertTeamBoxscoreRow('2007-09-10', 'TeamH', 1, 24, 22);
        $this->insertPlayerBoxscoreRow('2007-09-10', $pid++, 'P2007-2', 'PG', 24, 22, 24, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);
        $this->insertPlayerBoxscoreRow('2007-09-10', $pid++, 'P2007-3', 'PG', 24, 22, 22, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);

        // G2: 2007-12-20 visitor=22 home=24
        $this->insertTeamBoxscoreRow('2007-12-20', 'TeamV', 1, 22, 24);
        $this->insertTeamBoxscoreRow('2007-12-20', 'TeamH', 1, 22, 24);
        $this->insertPlayerBoxscoreRow('2007-12-20', $pid++, 'P2007-4', 'PG', 22, 24, 22, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);
        $this->insertPlayerBoxscoreRow('2007-12-20', $pid++, 'P2007-5', 'PG', 22, 24, 24, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);

        // G2p: 2007-10-20 visitor=22 home=24 (Oct phantom twin of G2; same fixed scores)
        $this->insertTeamBoxscoreRow('2007-10-20', 'TeamV', 1, 22, 24);
        $this->insertTeamBoxscoreRow('2007-10-20', 'TeamH', 1, 22, 24);
        $this->insertPlayerBoxscoreRow('2007-10-20', $pid++, 'P2007-6', 'PG', 22, 24, 22, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);
        $this->insertPlayerBoxscoreRow('2007-10-20', $pid++, 'P2007-7', 'PG', 22, 24, 24, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);

        // G3: 2007-10-15 visitor=24 home=22 (real Oct HEAT game, no Dec twin)
        $this->insertTeamBoxscoreRow('2007-10-15', 'TeamV', 1, 24, 22);
        $this->insertTeamBoxscoreRow('2007-10-15', 'TeamH', 1, 24, 22);
        $this->insertPlayerBoxscoreRow('2007-10-15', $pid++, 'P2007-8', 'PG', 24, 22, 24, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);
        $this->insertPlayerBoxscoreRow('2007-10-15', $pid++, 'P2007-9', 'PG', 24, 22, 22, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);

        // G4: 2007-12-05 visitor=24 home=22
        $this->insertTeamBoxscoreRow('2007-12-05', 'TeamV', 1, 24, 22);
        $this->insertTeamBoxscoreRow('2007-12-05', 'TeamH', 1, 24, 22);
        $this->insertPlayerBoxscoreRow('2007-12-05', $pid++, 'P2007-10', 'PG', 24, 22, 24, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);
        $this->insertPlayerBoxscoreRow('2007-12-05', $pid++, 'P2007-11', 'PG', 24, 22, 22, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);

        // G4n: 2007-10-05 visitor=24 home=22 (Oct game whose Dec twin G4 has different scores)
        $g4nVisitorId = $this->insertTeamBoxscoreRow('2007-10-05', 'TeamV', 1, 24, 22);
        $g4nHomeId    = $this->insertTeamBoxscoreRow('2007-10-05', 'TeamH', 1, 24, 22);
        $this->insertPlayerBoxscoreRow('2007-10-05', $pid++, 'P2007-12', 'PG', 24, 22, 24, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);
        $this->insertPlayerBoxscoreRow('2007-10-05', $pid,   'P2007-13', 'PG', 24, 22, 22, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid)]);

        // Diverge G4n scores from G4 so the twin check does not match.
        $this->db->query(
            "UPDATE ibl_box_scores_teams SET visitor_q1_points = visitor_q1_points + 7
             WHERE id IN ($g4nVisitorId, $g4nHomeId)"
        );

        return ['phantom_team_rows' => 4, 'phantom_player_rows' => 4, 'real_oct_team_rows' => 4];
    }

    /**
     * Seeds only the real games (G1, G2, G3, G4) — no phantoms.
     * Used by testNoOpWhenNoPhantomRows.
     */
    private function seedRealOnly(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $pid    = self::PID_BASE + $i;
            $teamid = ($i % 2 === 0) ? 24 : 22;
            $this->insertPidIgnore($pid, 'P2007-' . $i, $teamid);
        }

        $pid = self::PID_BASE;

        // G1: 2007-11-10 visitor=24 home=22
        $this->insertTeamBoxscoreRow('2007-11-10', 'TeamV', 1, 24, 22);
        $this->insertTeamBoxscoreRow('2007-11-10', 'TeamH', 1, 24, 22);
        $this->insertPlayerBoxscoreRow('2007-11-10', $pid++, 'P2007-0', 'PG', 24, 22, 24, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);
        $this->insertPlayerBoxscoreRow('2007-11-10', $pid++, 'P2007-1', 'PG', 24, 22, 22, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);

        // G2: 2007-12-20 visitor=22 home=24
        $this->insertTeamBoxscoreRow('2007-12-20', 'TeamV', 1, 22, 24);
        $this->insertTeamBoxscoreRow('2007-12-20', 'TeamH', 1, 22, 24);
        $this->insertPlayerBoxscoreRow('2007-12-20', $pid++, 'P2007-2', 'PG', 22, 24, 22, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);
        $this->insertPlayerBoxscoreRow('2007-12-20', $pid++, 'P2007-3', 'PG', 22, 24, 24, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);

        // G3: 2007-10-15 visitor=24 home=22
        $this->insertTeamBoxscoreRow('2007-10-15', 'TeamV', 1, 24, 22);
        $this->insertTeamBoxscoreRow('2007-10-15', 'TeamH', 1, 24, 22);
        $this->insertPlayerBoxscoreRow('2007-10-15', $pid++, 'P2007-4', 'PG', 24, 22, 24, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);
        $this->insertPlayerBoxscoreRow('2007-10-15', $pid++, 'P2007-5', 'PG', 24, 22, 22, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);

        // G4: 2007-12-05 visitor=24 home=22
        $this->insertTeamBoxscoreRow('2007-12-05', 'TeamV', 1, 24, 22);
        $this->insertTeamBoxscoreRow('2007-12-05', 'TeamH', 1, 24, 22);
        $this->insertPlayerBoxscoreRow('2007-12-05', $pid++, 'P2007-6', 'PG', 24, 22, 24, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid - 1)]);
        $this->insertPlayerBoxscoreRow('2007-12-05', $pid,   'P2007-7', 'PG', 24, 22, 22, overrides: ['game_of_that_day' => 1, 'uuid' => sprintf('test-2007-%d', $pid)]);
    }

    private function teamRowCount(string $date, int $visitor, int $home): int
    {
        $result = $this->db->query(
            "SELECT COUNT(*) FROM ibl_box_scores_teams
             WHERE game_date = '$date' AND visitor_teamid = $visitor AND home_teamid = $home"
        );
        self::assertNotFalse($result);
        $row = $result->fetch_row();
        $result->free();

        return (int) ($row[0] ?? 0);
    }

    private function playerRowCount(string $date, int $visitor, int $home): int
    {
        $result = $this->db->query(
            "SELECT COUNT(*) FROM ibl_box_scores
             WHERE game_date = '$date' AND visitor_teamid = $visitor AND home_teamid = $home"
        );
        self::assertNotFalse($result);
        $row = $result->fetch_row();
        $result->free();

        return (int) ($row[0] ?? 0);
    }

    private function backupCount(string $table): int
    {
        $safeTable = match ($table) {
            Phantom2007BoxscoreRepair::TEAM_BACKUP_TABLE   => Phantom2007BoxscoreRepair::TEAM_BACKUP_TABLE,
            Phantom2007BoxscoreRepair::PLAYER_BACKUP_TABLE => Phantom2007BoxscoreRepair::PLAYER_BACKUP_TABLE,
            default => throw new \InvalidArgumentException('Unknown backup table: ' . $table),
        };
        $result = $this->db->query(
            "SELECT COUNT(*) FROM $safeTable WHERE game_date BETWEEN '2007-09-01' AND '2007-10-31'"
        );
        self::assertNotFalse($result);
        $row = $result->fetch_row();
        $result->free();

        return (int) ($row[0] ?? 0);
    }

    // ─────────────────────────────────── assertPreconditions tests ────────────

    public function testAssertPreconditionsProceedsWithSeededPhantoms(): void
    {
        $expected = $this->seedAll();
        $repair   = $this->makeRepair($expected);

        self::assertSame('proceed', $repair->assertPreconditions());
    }

    public function testNoOpWhenNoPhantomRows(): void
    {
        $this->cleanAll();
        $this->seedRealOnly();

        $repair = $this->makeRepair(['phantom_team_rows' => 0, 'phantom_player_rows' => 0, 'real_oct_team_rows' => 2]);
        self::assertSame('noop', $repair->assertPreconditions());

        $result = $repair->runRepair(false);
        self::assertSame('noop', $result['status']);
        self::assertSame(0, $result['deleted']['teams']);
        self::assertSame(0, $result['deleted']['players']);

        // All 8 team rows still present across the 4 games.
        self::assertSame(2, $this->teamRowCount('2007-11-10', 24, 22));
        self::assertSame(2, $this->teamRowCount('2007-12-20', 22, 24));
        self::assertSame(2, $this->teamRowCount('2007-10-15', 24, 22));
        self::assertSame(2, $this->teamRowCount('2007-12-05', 24, 22));
    }

    public function testAbortsOnTeamCountMismatch(): void
    {
        $expected           = $this->seedAll();
        $expected['phantom_team_rows'] = 5;
        $repair             = $this->makeRepair($expected);

        $this->expectException(\RuntimeException::class);

        try {
            $repair->runRepair(false);
        } finally {
            // All rows intact; both backup tables empty.
            self::assertSame(14, $this->countLiveTeamRows());
            self::assertSame(14, $this->countLivePlayerRows());
            self::assertSame(0, $this->backupCount(Phantom2007BoxscoreRepair::TEAM_BACKUP_TABLE));
            self::assertSame(0, $this->backupCount(Phantom2007BoxscoreRepair::PLAYER_BACKUP_TABLE));
        }
    }

    public function testAbortsOnPlayerCountMismatch(): void
    {
        $expected                       = $this->seedAll();
        $expected['phantom_player_rows'] = 3;
        $repair                         = $this->makeRepair($expected);

        $this->expectException(\RuntimeException::class);

        try {
            $repair->runRepair(false);
        } finally {
            self::assertSame(14, $this->countLiveTeamRows());
            self::assertSame(14, $this->countLivePlayerRows());
            self::assertSame(0, $this->backupCount(Phantom2007BoxscoreRepair::TEAM_BACKUP_TABLE));
            self::assertSame(0, $this->backupCount(Phantom2007BoxscoreRepair::PLAYER_BACKUP_TABLE));
        }
    }

    public function testAbortsWhenRecapReferencesPhantom(): void
    {
        $expected = $this->seedAll();
        $repair   = $this->makeRepair($expected);

        // Seed a sim summary + recap at the G1p coordinate.
        $this->db->query('INSERT IGNORE INTO ibl_sim_summaries (sim, status) VALUES (' . self::TEST_SIM . ", 'done')");
        $this->insertRow('ibl_sim_game_recaps', [
            'sim'              => self::TEST_SIM,
            'season_year'      => 2007,
            'game_date'        => '2007-09-10',
            'visitor_teamid'   => 24,
            'home_teamid'      => 22,
            'game_of_that_day' => 1,
            'sort_order'       => 1,
            'recap_text'       => 'phantom recap',
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            $repair->runRepair(false);
        } finally {
            self::assertSame(14, $this->countLiveTeamRows());
            self::assertSame(14, $this->countLivePlayerRows());
        }
    }

    // ──────────────────────────────────── runRepair tests ─────────────────────

    public function testRunRepairDeletesOnlyPhantomRows(): void
    {
        $this->cleanAll();
        $expected = $this->seedAll();
        $repair   = $this->makeRepair($expected);

        $result = $repair->runRepair(false);

        self::assertSame('proceed', $result['status']);
        self::assertSame(4, $result['deleted']['teams']);
        self::assertSame(4, $result['deleted']['players']);

        // Phantom rows gone.
        self::assertSame(0, $this->teamRowCount('2007-09-10', 24, 22));
        self::assertSame(0, $this->teamRowCount('2007-10-20', 22, 24));
        self::assertSame(0, $this->playerRowCount('2007-09-10', 24, 22));
        self::assertSame(0, $this->playerRowCount('2007-10-20', 22, 24));

        // Real rows survive.
        self::assertSame(2, $this->teamRowCount('2007-11-10', 24, 22));
        self::assertSame(2, $this->teamRowCount('2007-12-20', 22, 24));
        self::assertSame(2, $this->teamRowCount('2007-10-15', 24, 22));
        self::assertSame(2, $this->teamRowCount('2007-12-05', 24, 22));
        self::assertSame(2, $this->teamRowCount('2007-10-05', 24, 22));
        self::assertSame(2, $this->playerRowCount('2007-11-10', 24, 22));
        self::assertSame(2, $this->playerRowCount('2007-12-20', 22, 24));
        self::assertSame(2, $this->playerRowCount('2007-10-15', 24, 22));
        self::assertSame(2, $this->playerRowCount('2007-12-05', 24, 22));
        self::assertSame(2, $this->playerRowCount('2007-10-05', 24, 22));
    }

    public function testRunRepairFillsBackupsWithDeletedRows(): void
    {
        $this->cleanAll();
        $expected = $this->seedAll();
        $repair   = $this->makeRepair($expected);

        $result = $repair->runRepair(false);

        self::assertSame('proceed', $result['status']);
        self::assertSame(4, $this->backupCount(Phantom2007BoxscoreRepair::TEAM_BACKUP_TABLE));
        self::assertSame(4, $this->backupCount(Phantom2007BoxscoreRepair::PLAYER_BACKUP_TABLE));
    }

    public function testDryRunRollsBackEverything(): void
    {
        $this->cleanAll();
        $expected = $this->seedAll();
        $repair   = $this->makeRepair($expected);

        $result = $repair->runRepair(true);

        self::assertSame('proceed', $result['status']);
        self::assertSame(4, $result['deleted']['teams']);
        self::assertSame(4, $result['deleted']['players']);

        // All 14 rows still live.
        self::assertSame(14, $this->countLiveTeamRows());
        self::assertSame(14, $this->countLivePlayerRows());

        // Both backup tables empty.
        self::assertSame(0, $this->backupCount(Phantom2007BoxscoreRepair::TEAM_BACKUP_TABLE));
        self::assertSame(0, $this->backupCount(Phantom2007BoxscoreRepair::PLAYER_BACKUP_TABLE));
    }

    // ─────────────────────────────────────── private helpers ──────────────────

    /** Count all live team rows seeded by this class. */
    private function countLiveTeamRows(): int
    {
        $result = $this->db->query(
            "SELECT COUNT(*) FROM ibl_box_scores_teams
             WHERE game_date IN ('2007-09-10','2007-10-20','2007-10-15','2007-10-05','2007-11-10','2007-12-20','2007-12-05')
               AND visitor_teamid IN (22,24) AND home_teamid IN (22,24)"
        );
        self::assertNotFalse($result);
        $row = $result->fetch_row();
        $result->free();

        return (int) ($row[0] ?? 0);
    }

    /** Count all live player rows seeded by this class. */
    private function countLivePlayerRows(): int
    {
        $result = $this->db->query(
            "SELECT COUNT(*) FROM ibl_box_scores
             WHERE game_date IN ('2007-09-10','2007-10-20','2007-10-15','2007-10-05','2007-11-10','2007-12-20','2007-12-05')
               AND visitor_teamid IN (22,24) AND home_teamid IN (22,24)"
        );
        self::assertNotFalse($result);
        $row = $result->fetch_row();
        $result->free();

        return (int) ($row[0] ?? 0);
    }
}
