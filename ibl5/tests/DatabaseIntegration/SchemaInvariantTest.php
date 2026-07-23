<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Locks in the database-layer invariants that already exist in the schema and
 * inventories the referential-integrity gaps that intentionally remain.
 *
 * This test adds NO new constraints. It is a regression guard: if a future
 * migration silently drops one of the foreign keys, CHECK constraints, or
 * UNIQUE keys enumerated below, the corresponding assertion fails. The
 * KNOWN_COLUMNS_WITHOUT_FK inventory converts tribal knowledge about which
 * `pid` / `teamid` columns lack a foreign key into a reviewable artifact and
 * is the queue for future per-gap follow-up PRs.
 *
 * The expected sets are derived from the live migrated schema (the same one the
 * db-integration CI lane builds), not from 000_baseline_schema.sql — the
 * baseline snapshot predates the snake_case column renames (e.g. tid -> teamid,
 * cy1..cy6 -> salary_yr1..salary_yr6).
 */
#[Group('database')]
class SchemaInvariantTest extends DatabaseTestCase
{
    /**
     * Every foreign key that must remain present in the schema.
     *
     * constraint_name => [table, column, referenced_table, referenced_column]
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    private const EXPECTED_FOREIGN_KEYS = [
        'fk_api_keys_auth_user' => ['ibl_api_keys', 'user_id', 'auth_users', 'id'],
        'fk_boxscore_home' => ['ibl_box_scores', 'home_teamid', 'ibl_team_info', 'teamid'],
        'fk_boxscore_player' => ['ibl_box_scores', 'pid', 'ibl_plr', 'pid'],
        'fk_boxscore_visitor' => ['ibl_box_scores', 'visitor_teamid', 'ibl_team_info', 'teamid'],
        'fk_boxscoreteam_home' => ['ibl_box_scores_teams', 'home_teamid', 'ibl_team_info', 'teamid'],
        'fk_boxscoreteam_visitor' => ['ibl_box_scores_teams', 'visitor_teamid', 'ibl_team_info', 'teamid'],
        'fk_cash_considerations_team' => ['ibl_cash_considerations', 'teamid', 'ibl_team_info', 'teamid'],
        'fk_demands_player' => ['ibl_demands', 'pid', 'ibl_plr', 'pid'],
        'fk_draft_tid' => ['ibl_draft', 'teamid', 'ibl_team_info', 'teamid'],
        'fk_draftpick_owner_teamid' => ['ibl_draft_picks', 'owner_teamid', 'ibl_team_info', 'teamid'],
        'fk_draftpick_teampick_teamid' => ['ibl_draft_picks', 'teampick_teamid', 'ibl_team_info', 'teamid'],
        'fk_faoffer_pid' => ['ibl_fa_offers', 'pid', 'ibl_plr', 'pid'],
        'fk_faoffer_tid' => ['ibl_fa_offers', 'teamid', 'ibl_team_info', 'teamid'],
        'fk_fs_franchise' => ['ibl_franchise_seasons', 'franchise_id', 'ibl_team_info', 'teamid'],
        'fk_gt_franchise' => ['ibl_gm_tenures', 'franchise_id', 'ibl_team_info', 'teamid'],
        'fk_league_config_teamid' => ['ibl_league_config', 'teamid', 'ibl_team_info', 'teamid'],
        'fk_olympics_boxscore_home' => ['ibl_olympics_box_scores', 'home_teamid', 'ibl_olympics_team_info', 'teamid'],
        'fk_olympics_boxscore_player' => ['ibl_olympics_box_scores', 'pid', 'ibl_plr', 'pid'],
        'fk_olympics_boxscore_visitor' => ['ibl_olympics_box_scores', 'visitor_teamid', 'ibl_olympics_team_info', 'teamid'],
        'fk_olympics_boxscoreteam_home' => ['ibl_olympics_box_scores_teams', 'home_teamid', 'ibl_olympics_team_info', 'teamid'],
        'fk_olympics_boxscoreteam_visitor' => ['ibl_olympics_box_scores_teams', 'visitor_teamid', 'ibl_olympics_team_info', 'teamid'],
        'fk_olympics_saved_dc_header' => ['ibl_olympics_saved_depth_chart_players', 'depth_chart_id', 'ibl_olympics_saved_depth_charts', 'id'],
        'fk_olympics_schedule_home' => ['ibl_olympics_schedule', 'home_teamid', 'ibl_olympics_team_info', 'teamid'],
        'fk_olympics_schedule_visitor' => ['ibl_olympics_schedule', 'visitor_teamid', 'ibl_olympics_team_info', 'teamid'],
        'fk_olympics_standings_team' => ['ibl_olympics_standings', 'teamid', 'ibl_olympics_team_info', 'teamid'],
        'fk_olympics_stats_pid' => ['ibl_olympics_stats', 'pid', 'ibl_plr', 'pid'],
        'fk_one_on_one_loser_pid' => ['ibl_one_on_one', 'loser_pid', 'ibl_plr', 'pid'],
        'fk_one_on_one_winner_pid' => ['ibl_one_on_one', 'winner_pid', 'ibl_plr', 'pid'],
        'fk_plr_team' => ['ibl_plr', 'teamid', 'ibl_team_info', 'teamid'],
        'fk_saved_dc_header' => ['ibl_saved_depth_chart_players', 'depth_chart_id', 'ibl_saved_depth_charts', 'id'],
        'fk_sgr_home' => ['ibl_sim_game_recaps', 'home_teamid', 'ibl_team_info', 'teamid'],
        'fk_sgr_sim' => ['ibl_sim_game_recaps', 'sim', 'ibl_sim_summaries', 'sim'],
        'fk_sgr_visitor' => ['ibl_sim_game_recaps', 'visitor_teamid', 'ibl_team_info', 'teamid'],
        'fk_schedule_home' => ['ibl_schedule', 'home_teamid', 'ibl_team_info', 'teamid'],
        'fk_schedule_visitor' => ['ibl_schedule', 'visitor_teamid', 'ibl_team_info', 'teamid'],
        'fk_standings_team' => ['ibl_standings', 'teamid', 'ibl_team_info', 'teamid'],
        'fk_trade_cash_offer' => ['ibl_trade_cash', 'trade_offer_id', 'ibl_trade_offers', 'id'],
        'fk_trade_info_offer' => ['ibl_trade_info', 'tradeofferid', 'ibl_trade_offers', 'id'],
        'fk_asg_votes_team' => ['ibl_votes_ASG', 'teamid', 'ibl_team_info', 'teamid'],
        'fk_eoy_votes_team' => ['ibl_votes_EOY', 'teamid', 'ibl_team_info', 'teamid'],
    ];

    /**
     * CHECK constraints that must remain on ibl_plr (contract-year and salary
     * bounds). Names reflect the post-migration-119 schema (salary_yr1..6).
     *
     * @var list<string>
     */
    private const EXPECTED_PLR_CHECK_CONSTRAINTS = [
        'chk_plr_cy',
        'chk_plr_cyt',
        'chk_plr_salary_yr1',
        'chk_plr_salary_yr2',
        'chk_plr_salary_yr3',
        'chk_plr_salary_yr4',
        'chk_plr_salary_yr5',
        'chk_plr_salary_yr6',
    ];

    /**
     * Critical identity UNIQUE keys that must remain present.
     *
     * @var array<string, array{0: string, 1: string}> label => [table, column]
     */
    private const EXPECTED_UNIQUE_KEYS = [
        'ibl_plr.uuid' => ['ibl_plr', 'uuid'],
        'ibl_team_info.uuid' => ['ibl_team_info', 'uuid'],
    ];

    /**
     * Non-unique performance indexes that must remain present, asserted by exact
     * column sequence. Added by migration 141 to fix three prod slow-query hot
     * spots (run 27093907413). A future migration dropping or reordering one of
     * these silently regresses the plan; this locks the column order.
     *
     * label => [table, expected GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)]
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const EXPECTED_INDEXES = [
        'ibl_plr_snapshots.idx_snapshot_phase_year' => ['ibl_plr_snapshots', 'snapshot_phase,season_year'],
        'ibl_box_scores.idx_pid_date' => ['ibl_box_scores', 'pid,game_date'],
        'ibl_trade_info.idx_trade_info_offer_from_to' => ['ibl_trade_info', 'tradeofferid,trade_from,trade_to'],
    ];

    /**
     * Inventory of base-table `pid` / `teamid` columns that reference a player
     * or team but intentionally carry NO foreign key today. Each is acceptable
     * because the owning table is a denormalized snapshot, an append-only
     * historical log, a record-book aggregate, or transient engine output —
     * none of which should cascade or block on the parent row's lifecycle.
     *
     * This is the explicit allowlist / queue for future per-gap FK PRs. The
     * `*.teamid` reference target ibl_team_info(teamid) and player target
     * ibl_plr(pid) are the authoritative columns and are excluded from this set.
     *
     * "table.column" => reason
     *
     * @var array<string, string>
     */
    private const KNOWN_COLUMNS_WITHOUT_FK = [
        'ibl_box_scores_engine_shadow.home_teamid' => 'engine debug shadow table; transient sim output, not referentially constrained',
        'ibl_box_scores_engine_shadow.pid' => 'engine debug shadow table; transient sim output, not referentially constrained',
        'ibl_box_scores_engine_shadow.teamid' => 'engine debug shadow table; transient sim output, not referentially constrained',
        'ibl_box_scores_engine_shadow.visitor_teamid' => 'engine debug shadow table; transient sim output, not referentially constrained',
        'ibl_box_scores_engine_shadow_teams.home_teamid' => 'engine debug shadow table; transient sim output, not referentially constrained',
        'ibl_box_scores_engine_shadow_teams.teamid' => 'engine debug shadow table; transient sim output, not referentially constrained',
        'ibl_box_scores_engine_shadow_teams.visitor_teamid' => 'engine debug shadow table; transient sim output, not referentially constrained',
        'ibl_cash_considerations.counterparty_teamid' => 'secondary team reference; the primary teamid column is FK-constrained',
        'ibl_hist.pid' => 'append-only transaction log; retains rows for deleted players (no cascade)',
        'ibl_hist.teamid' => 'append-only transaction log; team marker retained across franchise changes',
        'ibl_jsb_allstar_rosters.pid' => 'JSB sub-game archive; pid space populated by the JSB importer',
        'ibl_jsb_allstar_scores.pid' => 'JSB sub-game archive; pid space populated by the JSB importer',
        'ibl_jsb_draft_results.pid' => 'JSB sub-game archive; pid space populated by the JSB importer',
        'ibl_jsb_hall_of_fame.pid' => 'JSB sub-game archive; pid space populated by the JSB importer',
        'ibl_jsb_history.teamid' => 'JSB season-history snapshot; team marker retained for historical rows',
        'ibl_jsb_retired_players.pid' => 'JSB sub-game archive; pid space populated by the JSB importer',
        'ibl_jsb_transactions.from_teamid' => 'JSB transaction log; historical team marker, no cascade',
        'ibl_jsb_transactions.pid' => 'JSB transaction log; historical player marker, no cascade',
        'ibl_jsb_transactions.to_teamid' => 'JSB transaction log; historical team marker, no cascade',
        'ibl_olympics_career_avgs.pid' => 'denormalized Olympics career aggregate; rebuilt from box scores',
        'ibl_olympics_career_totals.pid' => 'denormalized Olympics career aggregate; rebuilt from box scores',
        'ibl_olympics_hist.pid' => 'Olympics append-only transaction log; retains rows for archived players',
        'ibl_olympics_hist.teamid' => 'Olympics append-only transaction log; historical team marker',
        'ibl_olympics_jsb_history.teamid' => 'Olympics JSB season-history snapshot; historical team marker',
        'ibl_olympics_jsb_transactions.from_teamid' => 'Olympics JSB transaction log; historical team marker, no cascade',
        'ibl_olympics_jsb_transactions.pid' => 'Olympics JSB transaction log; historical player marker, no cascade',
        'ibl_olympics_jsb_transactions.to_teamid' => 'Olympics JSB transaction log; historical team marker, no cascade',
        'ibl_olympics_plr.pid' => 'Olympics roster snapshot; references main ibl_plr but is archive-scoped',
        'ibl_olympics_plr.teamid' => 'Olympics roster snapshot; team marker kept loose for archived rosters',
        'ibl_olympics_plr_snapshots.pid' => 'Olympics point-in-time roster snapshot; archive, no cascade',
        'ibl_olympics_plr_snapshots.teamid' => 'Olympics point-in-time roster snapshot; archive, no cascade',
        'ibl_olympics_power.teamid' => 'Olympics power-ranking snapshot; rebuilt each cycle',
        'ibl_olympics_rcb_alltime_records.pid' => 'Olympics record-book aggregate; denormalized, rebuilt on demand',
        'ibl_olympics_rcb_alltime_records.teamid' => 'Olympics record-book aggregate; denormalized, rebuilt on demand',
        'ibl_olympics_rcb_season_records.pid' => 'Olympics record-book aggregate; denormalized, rebuilt on demand',
        'ibl_olympics_rcb_season_records.teamid' => 'Olympics record-book aggregate; denormalized, rebuilt on demand',
        'ibl_olympics_saved_depth_charts.teamid' => 'user-saved Olympics depth-chart snapshot; decoupled from team lifecycle',
        'ibl_olympics_saved_depth_chart_players.pid' => 'user-saved Olympics depth-chart snapshot; decoupled from roster lifecycle',
        'ibl_plb_snapshots.pid' => 'PLB archive import snapshot; references external/archived identities',
        'ibl_plb_snapshots.teamid' => 'PLB archive import snapshot; references external/archived identities',
        'ibl_plr_snapshots.pid' => 'point-in-time roster snapshot (phase archive); no cascade by design',
        'ibl_plr_snapshots.teamid' => 'point-in-time roster snapshot (phase archive); no cascade by design',
        'ibl_power.teamid' => 'power-ranking snapshot; rebuilt each cycle',
        'ibl_rcb_alltime_records.pid' => 'record-book aggregate; denormalized, rebuilt on demand',
        'ibl_rcb_alltime_records.teamid' => 'record-book aggregate; denormalized, rebuilt on demand',
        'ibl_rcb_season_records.pid' => 'record-book aggregate; denormalized, rebuilt on demand',
        'ibl_rcb_season_records.teamid' => 'record-book aggregate; denormalized, rebuilt on demand',
        'ibl_saved_depth_charts.teamid' => 'user-saved depth-chart snapshot; decoupled from team lifecycle',
        'ibl_saved_depth_chart_players.pid' => 'user-saved depth-chart snapshot; decoupled from roster lifecycle',
    ];

    /**
     * The authoritative `pid` / `teamid` columns that are reference targets, not
     * foreign references — excluded from the gap inventory.
     *
     * @var list<string>
     */
    private const REFERENCE_TARGET_COLUMNS = [
        'ibl_plr.pid',
        'ibl_team_info.teamid',
        'ibl_olympics_team_info.teamid',
    ];

    #[DataProvider('expectedForeignKeysProvider')]
    public function testExpectedForeignKeyIsPresent(
        string $constraint,
        string $table,
        string $column,
        string $refTable,
        string $refColumn,
    ): void {
        $stmt = $this->db->prepare(
            "SELECT kcu.TABLE_NAME, kcu.COLUMN_NAME,
                    kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME
             FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
             JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
               ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
              AND rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
             WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
               AND rc.CONSTRAINT_NAME = ?"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $constraint);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull(
            $row,
            "Foreign key '$constraint' is missing — a migration dropped it. "
            . "Expected $table.$column -> $refTable.$refColumn."
        );
        self::assertSame($table, $row['TABLE_NAME'], "FK '$constraint' is on an unexpected table.");
        self::assertSame($column, $row['COLUMN_NAME'], "FK '$constraint' is on an unexpected column.");
        self::assertSame($refTable, $row['REFERENCED_TABLE_NAME'], "FK '$constraint' references an unexpected table.");
        self::assertSame($refColumn, $row['REFERENCED_COLUMN_NAME'], "FK '$constraint' references an unexpected column.");
    }

    #[DataProvider('expectedPlrCheckConstraintsProvider')]
    public function testExpectedPlrCheckConstraintIsPresent(string $constraint): void
    {
        $stmt = $this->db->prepare(
            "SELECT CONSTRAINT_NAME
             FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'ibl_plr'
               AND CONSTRAINT_NAME = ?"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $constraint);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull(
            $row,
            "CHECK constraint '$constraint' is missing from ibl_plr — a migration dropped it."
        );
    }

    #[DataProvider('expectedUniqueKeysProvider')]
    public function testExpectedUniqueKeyIsPresent(string $table, string $column): void
    {
        $stmt = $this->db->prepare(
            "SELECT INDEX_NAME
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND NON_UNIQUE = 0"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull(
            $row,
            "UNIQUE key on $table.$column is missing — a migration dropped it."
        );
    }

    #[DataProvider('expectedIndexesProvider')]
    public function testExpectedIndexIsPresentWithColumnOrder(
        string $indexName,
        string $table,
        string $expectedColumns,
    ): void {
        $stmt = $this->db->prepare(
            "SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?
             GROUP BY INDEX_NAME"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('ss', $table, $indexName);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull(
            $row,
            "Index '$indexName' on $table is missing — migration 141 added it; a later "
            . "migration dropped it (regresses a prod slow-query fix)."
        );
        self::assertSame(
            $expectedColumns,
            $row['cols'],
            "Index '$indexName' on $table has the wrong column sequence — expected "
            . "($expectedColumns). Column order is load-bearing for the query plan."
        );
    }

    /**
     * Failure-case guard for migration 141's `DROP INDEX idx_pid`: proves the drop
     * did NOT orphan fk_boxscore_player. Two parts:
     *   (a) the standalone idx_pid is gone, and
     *   (b) some index on ibl_box_scores leads with `pid` at SEQ_IN_INDEX=1 — that is
     *       the new idx_pid_date composite, which now backs the FK.
     * (testExpectedForeignKeyIsPresent separately proves fk_boxscore_player resolves.)
     * idx_gt_pid leads with game_type, so it cannot false-satisfy part (b).
     */
    public function testBoxScoresPidIndexDropDidNotOrphanForeignKey(): void
    {
        $absent = $this->db->prepare(
            "SELECT INDEX_NAME
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'ibl_box_scores'
               AND INDEX_NAME = 'idx_pid'"
        );
        self::assertNotFalse($absent);
        $absent->execute();
        $absentRow = $absent->get_result()->fetch_assoc();
        $absent->close();
        self::assertNull(
            $absentRow,
            "Standalone idx_pid still exists on ibl_box_scores — migration 141 should "
            . "have dropped it (superseded by idx_pid_date's (pid) left-prefix)."
        );

        $backing = $this->db->prepare(
            "SELECT INDEX_NAME
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'ibl_box_scores'
               AND COLUMN_NAME = 'pid'
               AND SEQ_IN_INDEX = 1"
        );
        self::assertNotFalse($backing);
        $backing->execute();
        $backingRow = $backing->get_result()->fetch_assoc();
        $backing->close();
        self::assertNotNull(
            $backingRow,
            "No index on ibl_box_scores leads with `pid` — fk_boxscore_player would be "
            . "orphaned. idx_pid_date (pid, game_date) must back the FK after the drop."
        );
    }

    /**
     * Locks the FK-gap inventory against the live schema. Fails when a base
     * table gains or loses an un-FK'd pid/teamid column relative to the
     * documented allowlist, forcing a triage decision (add an FK, or allowlist
     * the column with a one-line reason here).
     */
    public function testFkGapInventoryMatchesLiveSchema(): void
    {
        $actual = $this->liveColumnsWithoutFk();
        $expected = array_keys(self::KNOWN_COLUMNS_WITHOUT_FK);

        sort($actual);
        sort($expected);

        $unexpected = array_diff($actual, $expected);
        $resolved = array_diff($expected, $actual);

        $messages = [];
        if (count($unexpected) > 0) {
            $messages[] = "New pid/teamid columns without a foreign key: " . implode(', ', $unexpected)
                . " — add an FK in a migration, or allowlist each with a one-line reason in "
                . "KNOWN_COLUMNS_WITHOUT_FK in "
                . "ibl5/tests/DatabaseIntegration/SchemaInvariantTest.php.";
        }
        if (count($resolved) > 0) {
            $messages[] = "Columns now covered by a foreign key (or removed) but still in the inventory: "
                . implode(', ', $resolved)
                . " — remove each stale entry from KNOWN_COLUMNS_WITHOUT_FK.";
        }

        self::assertSame($expected, $actual, implode("\n", $messages));
    }

    /**
     * Internal consistency: no (table, column) pair is both an expected FK and a
     * documented gap. Keyed on table.column, not table — several tables legally
     * have one FK'd and one un-FK'd pid/teamid column (e.g. ibl_box_scores has
     * FKs on pid/home_teamid/visitor_teamid but not on the denormalized teamid).
     */
    public function testInventoryAndExpectedFksAreDisjoint(): void
    {
        $fkColumns = [];
        foreach (self::EXPECTED_FOREIGN_KEYS as [$table, $column]) {
            $fkColumns["$table.$column"] = true;
        }

        $overlap = array_intersect(
            array_keys(self::KNOWN_COLUMNS_WITHOUT_FK),
            array_keys($fkColumns),
        );

        self::assertEmpty(
            $overlap,
            'These columns are listed as BOTH an expected FK and a known gap: '
            . implode(', ', $overlap)
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public static function expectedForeignKeysProvider(): array
    {
        $cases = [];
        foreach (self::EXPECTED_FOREIGN_KEYS as $constraint => [$table, $column, $refTable, $refColumn]) {
            $cases[$constraint] = [$constraint, $table, $column, $refTable, $refColumn];
        }
        return $cases;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function expectedPlrCheckConstraintsProvider(): array
    {
        $cases = [];
        foreach (self::EXPECTED_PLR_CHECK_CONSTRAINTS as $constraint) {
            $cases[$constraint] = [$constraint];
        }
        return $cases;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function expectedUniqueKeysProvider(): array
    {
        $cases = [];
        foreach (self::EXPECTED_UNIQUE_KEYS as $label => [$table, $column]) {
            $cases[$label] = [$table, $column];
        }
        return $cases;
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function expectedIndexesProvider(): array
    {
        $cases = [];
        foreach (self::EXPECTED_INDEXES as $label => [$table, $expectedColumns]) {
            // $label is "table.index_name"; the index name is the part after the dot.
            $indexName = substr($label, strrpos($label, '.') + 1);
            $cases[$label] = [$indexName, $table, $expectedColumns];
        }
        return $cases;
    }

    /**
     * Every base-table `pid` / `teamid`-family column that lacks a foreign key,
     * excluding the authoritative reference-target columns. Views are excluded
     * (they cannot carry foreign keys).
     *
     * @return list<string> "table.column"
     */
    private function liveColumnsWithoutFk(): array
    {
        $sql =
            "SELECT CONCAT(c.TABLE_NAME, '.', c.COLUMN_NAME) AS col
             FROM INFORMATION_SCHEMA.COLUMNS c
             JOIN INFORMATION_SCHEMA.TABLES t
               ON t.TABLE_SCHEMA = c.TABLE_SCHEMA
              AND t.TABLE_NAME = c.TABLE_NAME
              AND t.TABLE_TYPE = 'BASE TABLE'
             WHERE c.TABLE_SCHEMA = DATABASE()
               AND (c.COLUMN_NAME = 'pid'
                    OR c.COLUMN_NAME = 'tid'
                    OR c.COLUMN_NAME LIKE '%teamid%'
                    OR c.COLUMN_NAME = 'franchise_id')
               AND NOT EXISTS (
                   SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
                   WHERE k.TABLE_SCHEMA = c.TABLE_SCHEMA
                     AND k.TABLE_NAME = c.TABLE_NAME
                     AND k.COLUMN_NAME = c.COLUMN_NAME
                     AND k.REFERENCED_TABLE_NAME IS NOT NULL
               )";

        $result = $this->db->query($sql);
        self::assertInstanceOf(\mysqli_result::class, $result);

        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $col = $row['col'];
            self::assertIsString($col);
            if (in_array($col, self::REFERENCE_TARGET_COLUMNS, true)) {
                continue;
            }
            $columns[] = $col;
        }
        $result->free();

        return $columns;
    }
}
