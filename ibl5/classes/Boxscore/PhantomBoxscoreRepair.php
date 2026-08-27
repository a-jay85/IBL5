<?php

declare(strict_types=1);

namespace Boxscore;

/**
 * One-shot repair for phantom season-2008 boxscore rows.
 *
 * Two disjoint phantom sets are resolved:
 *   Set A - orphan games: boxscore rows with no matching `ibl_schedule` row on
 *           (season_year, game_date, visitor_teamid, home_teamid), excluding the
 *           off-schedule months and exempt teams that ScheduleMembershipGuard owns.
 *   Set B - duplicate triples: a scheduled game that carries more than one
 *           game_of_that_day. The copy whose reconstructed final score matches
 *           `ibl_schedule` is the keeper; every other copy is phantom.
 *
 * The class never guesses. If a duplicated triple does not resolve to exactly one
 * score-matched copy, the whole run aborts and deletes nothing.
 */
final class PhantomBoxscoreRepair extends \BaseMysqliRepository
{
    /**
     * Measured against the production snapshot for season 2008. The precondition
     * refuses to delete anything unless the live counts match these exactly.
     *
     * @var array<string, int>
     */
    private const EXPECTED = [
        'orphan_games' => 618,
        'orphan_team_rows' => 1236,
        'duplicate_triple_games' => 3,
        'duplicate_team_rows' => 6,
        'player_rows' => 14502,
        'recap_rows' => 20,
    ];

    /**
     * Session-scoped scratch table holding the resolved phantom keys. Every backup
     * INSERT and every DELETE joins this one table, so a backup can never target a
     * different row set than the delete that follows it.
     */
    private const DROP_KEY_TABLE = 'DROP TEMPORARY TABLE IF EXISTS `phantom_repair_keys`';

    private const CREATE_KEY_TABLE = 'CREATE TEMPORARY TABLE `phantom_repair_keys` ('
        . '`gd` DATE NOT NULL, `v` INT NOT NULL, `h` INT NOT NULL, `g` INT NOT NULL, '
        . '`src` VARCHAR(16) NOT NULL, PRIMARY KEY (`gd`, `v`, `h`, `g`)) ENGINE=InnoDB';

    private const INSERT_KEY = 'INSERT INTO `phantom_repair_keys` (`gd`, `v`, `h`, `g`, `src`) '
        . 'VALUES (?, ?, ?, ?, ?)';

    /**
     * Backup fills. Column lists are spelled out because both boxscore tables carry
     * GENERATED ALWAYS ... STORED columns (`game_type`, `season_year`, `calc_points`,
     * `calc_rebounds`, `calc_fg_made`), and a `SELECT *` would try to write them.
     *
     * They ARE copied into the backups, which migration 167 declares as ordinary
     * NULL-able columns precisely so they can be. That copy is never an insert source -
     * bin/rollback-phantom-repair omits them and lets MariaDB recompute them - it is the
     * verification source: after a rollback, the recomputed live values are compared
     * against these captured-before-the-delete values, and any drift means the restore
     * silently reconstructed something different from what was removed. Leaving them
     * NULL would make that comparison pass vacuously (`l.season_year <> NULL` is NULL,
     * never true), which is exactly the silently-wrong restore the check exists to catch.
     *
     * Each fill is guarded `NOT EXISTS (... x.id = <live>.id)` so it can run against a
     * backup that is already populated. Production applies migration 168 once, but an
     * operator who rolls back and then re-applies the repair would otherwise hit a
     * duplicate-key error on the backup's PRIMARY KEY. The guard skips rows already
     * captured rather than re-inserting them, and assertBackupCount() still requires the
     * backup total to equal the rows about to be deleted, so a partial backup is still
     * caught.
     */
    private const FILL_TEAM_BACKUP = 'INSERT INTO `ibl_box_scores_teams_phantom_backup` ('
        . '`id`, `game_date`, `name`, `game_of_that_day`, `visitor_teamid`, `home_teamid`, '
        . '`attendance`, `capacity`, `visitor_wins`, `visitor_losses`, `home_wins`, `home_losses`, '
        . '`visitor_q1_points`, `visitor_q2_points`, `visitor_q3_points`, `visitor_q4_points`, `visitor_ot_points`, '
        . '`home_q1_points`, `home_q2_points`, `home_q3_points`, `home_q4_points`, `home_ot_points`, '
        . '`game_min`, `game_2gm`, `game_2ga`, `game_ftm`, `game_fta`, `game_3gm`, `game_3ga`, '
        . '`game_orb`, `game_drb`, `game_ast`, `game_stl`, `game_tov`, `game_blk`, `game_pf`, '
        . '`created_at`, `updated_at`, '
        . '`game_type`, `season_year`, `calc_points`, `calc_rebounds`, `calc_fg_made`) '
        . 'SELECT b.`id`, b.`game_date`, b.`name`, b.`game_of_that_day`, b.`visitor_teamid`, b.`home_teamid`, '
        . 'b.`attendance`, b.`capacity`, b.`visitor_wins`, b.`visitor_losses`, b.`home_wins`, b.`home_losses`, '
        . 'b.`visitor_q1_points`, b.`visitor_q2_points`, b.`visitor_q3_points`, b.`visitor_q4_points`, b.`visitor_ot_points`, '
        . 'b.`home_q1_points`, b.`home_q2_points`, b.`home_q3_points`, b.`home_q4_points`, b.`home_ot_points`, '
        . 'b.`game_min`, b.`game_2gm`, b.`game_2ga`, b.`game_ftm`, b.`game_fta`, b.`game_3gm`, b.`game_3ga`, '
        . 'b.`game_orb`, b.`game_drb`, b.`game_ast`, b.`game_stl`, b.`game_tov`, b.`game_blk`, b.`game_pf`, '
        . 'b.`created_at`, b.`updated_at`, '
        . 'b.`game_type`, b.`season_year`, b.`calc_points`, b.`calc_rebounds`, b.`calc_fg_made` '
        . 'FROM `ibl_box_scores_teams` b '
        . 'JOIN `phantom_repair_keys` k ON b.`game_date` = k.`gd` AND b.`visitor_teamid` = k.`v` '
        . 'AND b.`home_teamid` = k.`h` AND b.`game_of_that_day` = k.`g` '
        . 'WHERE b.`season_year` = ? '
        . 'AND NOT EXISTS (SELECT 1 FROM `ibl_box_scores_teams_phantom_backup` x WHERE x.`id` = b.`id`)';

    private const FILL_PLAYER_BACKUP = 'INSERT INTO `ibl_box_scores_phantom_backup` ('
        . '`id`, `game_date`, `name`, `pos`, `pid`, `visitor_teamid`, `home_teamid`, '
        . '`game_min`, `game_2gm`, `game_2ga`, `game_ftm`, `game_fta`, `game_3gm`, `game_3ga`, '
        . '`game_orb`, `game_drb`, `game_ast`, `game_stl`, `game_tov`, `game_blk`, `game_pf`, '
        . '`created_at`, `updated_at`, `uuid`, `game_of_that_day`, `attendance`, `capacity`, '
        . '`visitor_wins`, `visitor_losses`, `home_wins`, `home_losses`, `teamid`, '
        . '`game_type`, `season_year`, `calc_points`, `calc_rebounds`, `calc_fg_made`) '
        . 'SELECT b.`id`, b.`game_date`, b.`name`, b.`pos`, b.`pid`, b.`visitor_teamid`, b.`home_teamid`, '
        . 'b.`game_min`, b.`game_2gm`, b.`game_2ga`, b.`game_ftm`, b.`game_fta`, b.`game_3gm`, b.`game_3ga`, '
        . 'b.`game_orb`, b.`game_drb`, b.`game_ast`, b.`game_stl`, b.`game_tov`, b.`game_blk`, b.`game_pf`, '
        . 'b.`created_at`, b.`updated_at`, b.`uuid`, b.`game_of_that_day`, b.`attendance`, b.`capacity`, '
        . 'b.`visitor_wins`, b.`visitor_losses`, b.`home_wins`, b.`home_losses`, b.`teamid`, '
        . 'b.`game_type`, b.`season_year`, b.`calc_points`, b.`calc_rebounds`, b.`calc_fg_made` '
        . 'FROM `ibl_box_scores` b '
        . 'JOIN `phantom_repair_keys` k ON b.`game_date` = k.`gd` AND b.`visitor_teamid` = k.`v` '
        . 'AND b.`home_teamid` = k.`h` AND b.`game_of_that_day` = k.`g` '
        . 'WHERE b.`season_year` = ? '
        . 'AND NOT EXISTS (SELECT 1 FROM `ibl_box_scores_phantom_backup` x WHERE x.`id` = b.`id`)';

    private const FILL_RECAP_BACKUP = 'INSERT INTO `ibl_sim_game_recaps_phantom_backup` ('
        . '`id`, `sim`, `season_year`, `game_date`, `visitor_teamid`, `home_teamid`, '
        . '`game_of_that_day`, `box_id`, `sort_order`, `recap_text`, `created_at`) '
        . 'SELECT r.`id`, r.`sim`, r.`season_year`, r.`game_date`, r.`visitor_teamid`, r.`home_teamid`, '
        . 'r.`game_of_that_day`, r.`box_id`, r.`sort_order`, r.`recap_text`, r.`created_at` '
        . 'FROM `ibl_sim_game_recaps` r '
        . 'JOIN `phantom_repair_keys` k ON r.`game_date` = k.`gd` AND r.`visitor_teamid` = k.`v` '
        . 'AND r.`home_teamid` = k.`h` AND r.`game_of_that_day` = k.`g` '
        . 'WHERE r.`season_year` = ? '
        . 'AND NOT EXISTS (SELECT 1 FROM `ibl_sim_game_recaps_phantom_backup` x WHERE x.`id` = r.`id`)';

    private const COUNT_TEAM_ROWS_BY_SOURCE = 'SELECT COUNT(*) AS c FROM `ibl_box_scores_teams` b '
        . 'JOIN `phantom_repair_keys` k ON b.`game_date` = k.`gd` AND b.`visitor_teamid` = k.`v` '
        . 'AND b.`home_teamid` = k.`h` AND b.`game_of_that_day` = k.`g` '
        . 'WHERE b.`season_year` = ? AND k.`src` = ?';

    private const COUNT_TEAM_ROWS = 'SELECT COUNT(*) AS c FROM `ibl_box_scores_teams` b '
        . 'JOIN `phantom_repair_keys` k ON b.`game_date` = k.`gd` AND b.`visitor_teamid` = k.`v` '
        . 'AND b.`home_teamid` = k.`h` AND b.`game_of_that_day` = k.`g` '
        . 'WHERE b.`season_year` = ?';

    private const COUNT_PLAYER_ROWS = 'SELECT COUNT(*) AS c FROM `ibl_box_scores` b '
        . 'JOIN `phantom_repair_keys` k ON b.`game_date` = k.`gd` AND b.`visitor_teamid` = k.`v` '
        . 'AND b.`home_teamid` = k.`h` AND b.`game_of_that_day` = k.`g` '
        . 'WHERE b.`season_year` = ?';

    private const COUNT_RECAP_ROWS = 'SELECT COUNT(*) AS c FROM `ibl_sim_game_recaps` r '
        . 'JOIN `phantom_repair_keys` k ON r.`game_date` = k.`gd` AND r.`visitor_teamid` = k.`v` '
        . 'AND r.`home_teamid` = k.`h` AND r.`game_of_that_day` = k.`g` '
        . 'WHERE r.`season_year` = ?';

    private const DELETE_TEAM_ROWS = 'DELETE b FROM `ibl_box_scores_teams` b '
        . 'JOIN `phantom_repair_keys` k ON b.`game_date` = k.`gd` AND b.`visitor_teamid` = k.`v` '
        . 'AND b.`home_teamid` = k.`h` AND b.`game_of_that_day` = k.`g` '
        . 'WHERE b.`season_year` = ?';

    private const DELETE_PLAYER_ROWS = 'DELETE b FROM `ibl_box_scores` b '
        . 'JOIN `phantom_repair_keys` k ON b.`game_date` = k.`gd` AND b.`visitor_teamid` = k.`v` '
        . 'AND b.`home_teamid` = k.`h` AND b.`game_of_that_day` = k.`g` '
        . 'WHERE b.`season_year` = ?';

    /**
     * The recap predicate deliberately binds `game_of_that_day` alongside the triple.
     * `ibl_sim_game_recaps` declares UNIQUE KEY `uniq_game`
     * (season_year, game_date, visitor_teamid, home_teamid, game_of_that_day), so
     * game_of_that_day IS part of this table's row identity - unlike `ibl_schedule`,
     * which has no such column and is why gotd never appears in a schedule join.
     * Binding on the triple alone would sweep the three surviving duplicate-triple
     * keepers' recaps (23 rows instead of 20) and destroy legitimate data.
     */
    private const DELETE_RECAP_ROWS = 'DELETE r FROM `ibl_sim_game_recaps` r '
        . 'JOIN `phantom_repair_keys` k ON r.`game_date` = k.`gd` AND r.`visitor_teamid` = k.`v` '
        . 'AND r.`home_teamid` = k.`h` AND r.`game_of_that_day` = k.`g` '
        . 'WHERE r.`season_year` = ?';

    private const COUNT_TEAM_BACKUP = 'SELECT COUNT(*) AS c FROM `ibl_box_scores_teams_phantom_backup`';

    private const COUNT_PLAYER_BACKUP = 'SELECT COUNT(*) AS c FROM `ibl_box_scores_phantom_backup`';

    private const COUNT_RECAP_BACKUP = 'SELECT COUNT(*) AS c FROM `ibl_sim_game_recaps_phantom_backup`';

    private const IS_SCHEDULED = 'SELECT 1 AS c FROM `ibl_schedule` s WHERE s.`season_year` = ? '
        . 'AND s.`game_date` = ? AND s.`visitor_teamid` = ? AND s.`home_teamid` = ? LIMIT 1';

    /**
     * One row per copy of a duplicated triple, represented by its lowest-id row, with a
     * flag saying whether its reconstructed final score matches `ibl_schedule`. The join
     * to `ibl_schedule` is on (season_year, game_date, visitor_teamid, home_teamid) only.
     */
    private const SCORE_MATCHED_COPIES = 'SELECT b.`game_of_that_day` AS gotd, '
        . '((b.`visitor_q1_points` + b.`visitor_q2_points` + b.`visitor_q3_points` + b.`visitor_q4_points` '
        . '+ COALESCE(b.`visitor_ot_points`, 0)) = s.`visitor_score` '
        . 'AND (b.`home_q1_points` + b.`home_q2_points` + b.`home_q3_points` + b.`home_q4_points` '
        . '+ COALESCE(b.`home_ot_points`, 0)) = s.`home_score`) AS score_matches '
        . 'FROM `ibl_box_scores_teams` b '
        . 'JOIN `ibl_schedule` s ON s.`season_year` = b.`season_year` AND s.`game_date` = b.`game_date` '
        . 'AND s.`visitor_teamid` = b.`visitor_teamid` AND s.`home_teamid` = b.`home_teamid` '
        . 'WHERE b.`season_year` = ? AND b.`game_date` = ? AND b.`visitor_teamid` = ? AND b.`home_teamid` = ? '
        . 'AND b.`id` = (SELECT MIN(b2.`id`) FROM `ibl_box_scores_teams` b2 '
        . 'WHERE b2.`game_date` = b.`game_date` AND b2.`visitor_teamid` = b.`visitor_teamid` '
        . 'AND b2.`home_teamid` = b.`home_teamid` AND b2.`game_of_that_day` = b.`game_of_that_day`)';

    private readonly BoxscoreRepository $repository;

    private readonly bool $manageTransaction;

    /** @var list<array{game_date: string, visitor_teamid: int, home_teamid: int, keeper_gotd: int, candidate_gotds: list<int>}> */
    private array $duplicateResolutions = [];

    /**
     * The precondition counts this instance gates on. Production always uses
     * self::EXPECTED; only the database integration tests substitute their own.
     *
     * @var array{orphan_games: int, orphan_team_rows: int, duplicate_triple_games: int, duplicate_team_rows: int, player_rows: int, recap_rows: int}
     */
    private readonly array $expected;

    /**
     * @param bool $manageTransaction When false, deletePhantomRows() does not open,
     *                                commit or roll back a transaction and instead runs
     *                                inside the caller's. Required for the database
     *                                integration tests: DatabaseTestCase::setUp() already
     *                                opened a transaction, and MariaDB has no nested
     *                                transactions - an inner begin_transaction() would
     *                                implicitly COMMIT the test's outer one and leave the
     *                                test database permanently mutated.
     * @param array{orphan_games: int, orphan_team_rows: int, duplicate_triple_games: int, duplicate_team_rows: int, player_rows: int, recap_rows: int}|null $expectedOverride
     *        TEST-ONLY seam, exactly parallel to $manageTransaction. Substitutes the counts
     *        assertPreconditions() gates on, so the tri-state gate can be exercised against a
     *        small self-seeded fixture. Both runners of the `database` group - bin/db-test-up
     *        and CI's db-integration job - build the schema from migrations plus
     *        tests/DatabaseIntegration/Fixtures/db-seed.sql, which contains no boxscore rows
     *        at all, so the production snapshot's 618/1236/3/6/14502/20 is unreachable there.
     *        This is NOT the precondition-loosening assertPreconditions() forbids: production
     *        callers (migration 168, bin/check-boxscore-schedule) pass null and are gated on
     *        self::EXPECTED, unchanged. Passing a wrong override makes the gate stricter or
     *        differently-shaped, never absent - the throw path is still the only way past a
     *        mismatch.
     */
    public function __construct(
        \mysqli $db,
        BoxscoreRepository $repository,
        bool $manageTransaction = true,
        ?array $expectedOverride = null,
    ) {
        parent::__construct($db);
        $this->repository = $repository;
        $this->manageTransaction = $manageTransaction;
        $this->expected = $expectedOverride ?? self::EXPECTED;
    }

    /**
     * Which copy of each duplicated triple was kept, and which were on the table.
     *
     * Reporting only - populated as a side effect of the most recent
     * findPhantomTeamRows() call, so migration 168 can print the keeper alongside the
     * deletions without re-deriving them (and so a human reading the CI log can see
     * that the kept copy is the score-matched one, not merely the lowest gotd).
     *
     * @return list<array{game_date: string, visitor_teamid: int, home_teamid: int, keeper_gotd: int, candidate_gotds: list<int>}>
     */
    public function describeDuplicateResolutions(): array
    {
        return $this->duplicateResolutions;
    }

    /**
     * Every phantom row key, set A followed by set B. Pure read; never writes to
     * a permanent table.
     *
     * @return list<array{game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, source: string}>
     */
    public function findPhantomTeamRows(int $seasonYear): array
    {
        $setA = [];
        foreach ($this->repository->findOrphanBoxscoreGames($seasonYear) as $game) {
            $setA[] = [
                'game_date' => (string) $game['game_date'],
                'visitor_teamid' => (int) $game['visitor_teamid'],
                'home_teamid' => (int) $game['home_teamid'],
                'game_of_that_day' => (int) $game['game_of_that_day'],
                'source' => 'orphan',
            ];
        }

        $setB = $this->resolveDuplicateSurvivors($seasonYear);

        $this->assertDisjoint($setA, $setB);

        return array_merge($setA, $setB);
    }

    /**
     * @return array{orphan_games: int, orphan_team_rows: int, duplicate_triple_games: int, duplicate_team_rows: int, player_rows: int, recap_rows: int}
     */
    public function countAffectedRows(int $seasonYear): array
    {
        $keys = $this->findPhantomTeamRows($seasonYear);
        $this->buildKeyTable($keys);

        $orphanGames = 0;
        $duplicateTriples = [];
        foreach ($keys as $key) {
            if ($key['source'] === 'orphan') {
                $orphanGames++;
                continue;
            }
            $duplicateTriples[$key['game_date'] . '|' . $key['visitor_teamid'] . '|' . $key['home_teamid']] = true;
        }

        return [
            'orphan_games' => $orphanGames,
            'orphan_team_rows' => $this->countBySource($seasonYear, 'orphan'),
            'duplicate_triple_games' => count($duplicateTriples),
            'duplicate_team_rows' => $this->countBySource($seasonYear, 'duplicate'),
            'player_rows' => $this->scalarCount(self::COUNT_PLAYER_ROWS, 'i', $seasonYear),
            'recap_rows' => $this->scalarCount(self::COUNT_RECAP_ROWS, 'i', $seasonYear),
        ];
    }

    /**
     * Tri-state gate in front of every delete.
     *
     * @return string 'noop' when there is nothing to repair, 'proceed' when the live
     *                counts match EXPECTED exactly.
     * @throws \RuntimeException when the snapshot differs from EXPECTED in any way.
     */
    public function assertPreconditions(int $seasonYear): string
    {
        $counts = $this->countAffectedRows($seasonYear);

        $nothingToDo = true;
        foreach ($this->expected as $name => $_expected) {
            if ($counts[$name] !== 0) {
                $nothingToDo = false;
                break;
            }
        }
        if ($nothingToDo) {
            return 'noop';
        }

        $mismatches = [];
        foreach ($this->expected as $name => $expected) {
            if ($counts[$name] !== $expected) {
                $mismatches[] = sprintf('%s: expected %d, found %d', $name, $expected, $counts[$name]);
            }
        }
        if ($mismatches !== []) {
            throw new \RuntimeException(
                'Phantom repair precondition failed for season ' . $seasonYear . ' - refusing to delete anything. '
                . implode('; ', $mismatches)
                . '. Re-measure the snapshot and update PhantomBoxscoreRepair::EXPECTED; never loosen this check.'
            );
        }

        return 'proceed';
    }

    /**
     * Fill the backup tables, assert the backups captured everything, then delete.
     * All three steps run in one transaction so a backup can never diverge from a delete.
     *
     * @return array{state: string, team_rows: int, player_rows: int, recap_rows: int}
     */
    public function deletePhantomRows(int $seasonYear): array
    {
        if (!$this->manageTransaction) {
            return $this->runRepair($seasonYear);
        }

        /** @var array{state: string, team_rows: int, player_rows: int, recap_rows: int} $result */
        $result = $this->transactional(fn (): array => $this->runRepair($seasonYear));

        return $result;
    }

    /**
     * The repair body. Always runs inside a transaction - either the one
     * deletePhantomRows() opened via transactional(), or the caller's when the
     * manageTransaction seam is off.
     *
     * @return array{state: string, team_rows: int, player_rows: int, recap_rows: int}
     */
    private function runRepair(int $seasonYear): array
    {
        $state = $this->assertPreconditions($seasonYear);
        if ($state === 'noop') {
            return ['state' => 'noop', 'team_rows' => 0, 'player_rows' => 0, 'recap_rows' => 0];
        }

        $teamsToDelete = $this->scalarCount(self::COUNT_TEAM_ROWS, 'i', $seasonYear);
        $playersToDelete = $this->scalarCount(self::COUNT_PLAYER_ROWS, 'i', $seasonYear);
        $recapsToDelete = $this->scalarCount(self::COUNT_RECAP_ROWS, 'i', $seasonYear);

        $this->execute(self::FILL_TEAM_BACKUP, 'i', $seasonYear);
        $this->execute(self::FILL_PLAYER_BACKUP, 'i', $seasonYear);
        $this->execute(self::FILL_RECAP_BACKUP, 'i', $seasonYear);

        $this->assertBackupCount(self::COUNT_TEAM_BACKUP, 'ibl_box_scores_teams_phantom_backup', $teamsToDelete);
        $this->assertBackupCount(self::COUNT_PLAYER_BACKUP, 'ibl_box_scores_phantom_backup', $playersToDelete);
        $this->assertBackupCount(self::COUNT_RECAP_BACKUP, 'ibl_sim_game_recaps_phantom_backup', $recapsToDelete);

        // Leaves first, identity anchor last: ibl_box_scores -> ibl_sim_game_recaps ->
        // ibl_box_scores_teams. All three tables are InnoDB so the surrounding
        // transaction covers the whole sequence, but the order still matters for the
        // failure semantics of a re-run: the team rows are what identify a phantom
        // game, so leaving them until last means an abort mid-flight re-derives the
        // exact same key set on the next attempt.
        $playersDeleted = $this->execute(self::DELETE_PLAYER_ROWS, 'i', $seasonYear);
        $recapsDeleted = $this->execute(self::DELETE_RECAP_ROWS, 'i', $seasonYear);
        $teamsDeleted = $this->execute(self::DELETE_TEAM_ROWS, 'i', $seasonYear);

        if (
            $teamsDeleted !== $teamsToDelete
            || $playersDeleted !== $playersToDelete
            || $recapsDeleted !== $recapsToDelete
        ) {
            throw new \RuntimeException(sprintf(
                'Phantom repair delete diverged from its backup: teams %d/%d, players %d/%d, recaps %d/%d.',
                $teamsDeleted,
                $teamsToDelete,
                $playersDeleted,
                $playersToDelete,
                $recapsDeleted,
                $recapsToDelete
            ));
        }

        return [
            'state' => 'repaired',
            'team_rows' => $teamsDeleted,
            'player_rows' => $playersDeleted,
            'recap_rows' => $recapsDeleted,
        ];
    }

    /**
     * Losing game_of_that_day values for scheduled games that carry more than one copy.
     *
     * A duplicated triple is skipped (left to set A, or left alone entirely) when it falls in
     * an off-schedule month, involves an exempt team, or has no `ibl_schedule` row at all.
     * Skipped triples are not counted and not deleted here.
     *
     * @return list<array{game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, source: string}>
     * @throws \RuntimeException when a triple does not resolve to exactly one score-matched copy.
     */
    private function resolveDuplicateSurvivors(int $seasonYear): array
    {
        $losers = [];
        $this->duplicateResolutions = [];

        foreach ($this->repository->findDuplicateTripleGames($seasonYear) as $triple) {
            $gameDate = (string) $triple['game_date'];
            $visitorTeamId = (int) $triple['visitor_teamid'];
            $homeTeamId = (int) $triple['home_teamid'];

            $timestamp = strtotime($gameDate);
            $month = $timestamp === false ? 0 : (int) date('n', $timestamp);
            if (in_array($month, ScheduleMembershipGuard::OFF_SCHEDULE_MONTHS, true)) {
                continue;
            }
            if (
                in_array($visitorTeamId, ScheduleMembershipGuard::EXEMPT_TEAMIDS, true)
                || in_array($homeTeamId, ScheduleMembershipGuard::EXEMPT_TEAMIDS, true)
            ) {
                continue;
            }
            if (!$this->isScheduled($seasonYear, $gameDate, $visitorTeamId, $homeTeamId)) {
                continue;
            }

            $copies = $this->scoreMatchedCopies($seasonYear, $gameDate, $visitorTeamId, $homeTeamId);

            $keepers = [];
            foreach ($copies as $gotd => $matches) {
                if ($matches) {
                    $keepers[] = $gotd;
                }
            }

            if (count($keepers) !== 1) {
                throw new \RuntimeException(sprintf(
                    'Duplicate triple %s %d@%d resolved to %d score-matched copies (expected exactly 1) - '
                    . 'aborting the entire repair, nothing deleted. Candidate game_of_that_day values: %s.',
                    $gameDate,
                    $visitorTeamId,
                    $homeTeamId,
                    count($keepers),
                    implode(', ', array_map('strval', array_keys($copies)))
                ));
            }

            $this->duplicateResolutions[] = [
                'game_date' => $gameDate,
                'visitor_teamid' => $visitorTeamId,
                'home_teamid' => $homeTeamId,
                'keeper_gotd' => $keepers[0],
                'candidate_gotds' => array_map('intval', array_keys($copies)),
            ];

            foreach (array_keys($copies) as $gotd) {
                if ($gotd === $keepers[0]) {
                    continue;
                }
                $losers[] = [
                    'game_date' => $gameDate,
                    'visitor_teamid' => $visitorTeamId,
                    'home_teamid' => $homeTeamId,
                    'game_of_that_day' => $gotd,
                    'source' => 'duplicate',
                ];
            }
        }

        return $losers;
    }

    private function isScheduled(int $seasonYear, string $gameDate, int $visitorTeamId, int $homeTeamId): bool
    {
        return $this->fetchOne(self::IS_SCHEDULED, 'isii', $seasonYear, $gameDate, $visitorTeamId, $homeTeamId) !== null;
    }

    /**
     * @return array<int, bool> game_of_that_day => does its reconstructed score match the schedule
     */
    private function scoreMatchedCopies(int $seasonYear, string $gameDate, int $visitorTeamId, int $homeTeamId): array
    {
        $rows = $this->fetchAll(
            self::SCORE_MATCHED_COPIES,
            'isii',
            $seasonYear,
            $gameDate,
            $visitorTeamId,
            $homeTeamId
        );

        $copies = [];
        foreach ($rows as $row) {
            $copies[self::toInt($row['gotd'] ?? null)] = self::toInt($row['score_matches'] ?? null) === 1;
        }

        return $copies;
    }

    /**
     * @param list<array{game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, source: string}> $setA
     * @param list<array{game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, source: string}> $setB
     */
    private function assertDisjoint(array $setA, array $setB): void
    {
        $seen = [];
        foreach ($setA as $key) {
            $seen[$this->keyString($key)] = true;
        }

        $overlap = [];
        foreach ($setB as $key) {
            if (isset($seen[$this->keyString($key)])) {
                $overlap[] = $this->keyString($key);
            }
        }

        if ($overlap !== []) {
            throw new \RuntimeException(
                'Phantom sets A and B overlap on ' . implode(', ', $overlap)
                . ' - the set-B guard let a triple through that set A already covers. '
                . 'Fix the guard; do not de-duplicate silently.'
            );
        }
    }

    /**
     * @param array{game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, source: string} $key
     */
    private function keyString(array $key): string
    {
        return $key['game_date'] . '|' . $key['visitor_teamid'] . '|'
            . $key['home_teamid'] . '|' . $key['game_of_that_day'];
    }

    /**
     * @param list<array{game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, source: string}> $keys
     */
    private function buildKeyTable(array $keys): void
    {
        $this->execute(self::DROP_KEY_TABLE);
        $this->execute(self::CREATE_KEY_TABLE);

        foreach ($keys as $key) {
            $this->execute(
                self::INSERT_KEY,
                'siiis',
                $key['game_date'],
                $key['visitor_teamid'],
                $key['home_teamid'],
                $key['game_of_that_day'],
                $key['source']
            );
        }
    }

    private function countBySource(int $seasonYear, string $source): int
    {
        return $this->scalarCount(self::COUNT_TEAM_ROWS_BY_SOURCE, 'is', $seasonYear, $source);
    }

    private function scalarCount(string $query, string $types = '', mixed ...$params): int
    {
        $row = $this->fetchOne($query, $types, ...$params);

        return $row === null ? 0 : self::toInt($row['c'] ?? null);
    }

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function assertBackupCount(string $query, string $backupTable, int $expected): void
    {
        $actual = $this->scalarCount($query);

        if ($actual !== $expected) {
            throw new \RuntimeException(sprintf(
                'Backup table %s holds %d rows but %d are about to be deleted - aborting, nothing deleted.',
                $backupTable,
                $actual,
                $expected
            ));
        }
    }
}
