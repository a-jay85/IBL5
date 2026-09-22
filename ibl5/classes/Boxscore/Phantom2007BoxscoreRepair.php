<?php

declare(strict_types=1);

namespace Boxscore;

use mysqli;
use RuntimeException;

/**
 * One-shot repair for the 2007 phantom preseason and HEAT box-score rows.
 *
 * A 2026-09-18 import batch wrote Nov 2007 games shifted to Sep 2007 and Dec
 * 2007 games shifted to Oct 2007. A (date, visitor, home) triple is unique in
 * this league, so no Sep 2007 box-score is a real preseason game — they are all
 * phantoms. Oct 2007 rows are phantom when a Dec 2007 twin exists at the same
 * teams, team name, and all ten quarter scores; real HEAT games share the month
 * and must survive. This class backs up and deletes only the phantom rows.
 * Driver: migration 184. Backup tables: migration 183.
 */
final class Phantom2007BoxscoreRepair
{
    public const SEP_START = '2007-09-01';
    public const SEP_END   = '2007-09-30';
    public const OCT_START = '2007-10-01';
    public const OCT_END   = '2007-10-31';

    public const PHANTOM_TEAM_ROWS   = 830;
    public const PHANTOM_PLAYER_ROWS = 9796;
    public const REAL_OCT_TEAM_ROWS  = 278;

    public const TEAM_TABLE          = 'ibl_box_scores_teams';
    public const PLAYER_TABLE        = 'ibl_box_scores';
    public const TEAM_BACKUP_TABLE   = 'ibl_box_scores_teams_season2007_phantom_backup';
    public const PLAYER_BACKUP_TABLE = 'ibl_box_scores_season2007_phantom_backup';
    private const RECAP_TABLE        = 'ibl_sim_game_recaps';

    private const EXPECTED = [
        'phantom_team_rows'   => self::PHANTOM_TEAM_ROWS,
        'phantom_player_rows' => self::PHANTOM_PLAYER_ROWS,
        'real_oct_team_rows'  => self::REAL_OCT_TEAM_ROWS,
    ];

    // Over alias `t` (a team row): the Dec 2007 twin exists at the same teams,
    // name, and all ten quarter/OT scores. Uses <=> so NULL OT columns compare equal.
    private const OCT_TWIN_EXISTS = "EXISTS (
        SELECT 1 FROM `ibl_box_scores_teams` r
        WHERE r.game_date = t.game_date + INTERVAL 2 MONTH
          AND r.visitor_teamid  = t.visitor_teamid
          AND r.home_teamid     = t.home_teamid
          AND r.name            = t.name
          AND r.visitor_q1_points <=> t.visitor_q1_points
          AND r.visitor_q2_points <=> t.visitor_q2_points
          AND r.visitor_q3_points <=> t.visitor_q3_points
          AND r.visitor_q4_points <=> t.visitor_q4_points
          AND r.visitor_ot_points <=> t.visitor_ot_points
          AND r.home_q1_points    <=> t.home_q1_points
          AND r.home_q2_points    <=> t.home_q2_points
          AND r.home_q3_points    <=> t.home_q3_points
          AND r.home_q4_points    <=> t.home_q4_points
          AND r.home_ot_points    <=> t.home_ot_points
    )";

    // Over alias `t`: Sep rows are all phantom; Oct rows are phantom only when a
    // Dec twin exists. BETWEEN on game_date so idx_date is usable.
    private const PHANTOM_TEAM_WHERE = "(
        (t.game_date BETWEEN '2007-09-01' AND '2007-09-30')
        OR (t.game_date BETWEEN '2007-10-01' AND '2007-10-31' AND " . self::OCT_TWIN_EXISTS . ")
    )";

    // Over alias `p` (a player row): Sep rows are all phantom; Oct rows are phantom
    // when the parent team coordinate is phantom.
    private const PHANTOM_PLAYER_WHERE = "(
        (p.game_date BETWEEN '2007-09-01' AND '2007-09-30')
        OR (p.game_date BETWEEN '2007-10-01' AND '2007-10-31' AND EXISTS (
            SELECT 1 FROM `ibl_box_scores_teams` t
            WHERE t.game_date     = p.game_date
              AND t.visitor_teamid = p.visitor_teamid
              AND t.home_teamid    = p.home_teamid
              AND " . self::OCT_TWIN_EXISTS . "
        ))
    )";

    private mysqli $db;

    /** @var array{phantom_team_rows: int, phantom_player_rows: int, real_oct_team_rows: int} */
    private array $expected;

    /**
     * @param array{phantom_team_rows: int, phantom_player_rows: int, real_oct_team_rows: int}|null $expectedOverride
     *        Test-only seam: lets an integration fixture assert against its own seeded counts.
     */
    public function __construct(mysqli $db, ?array $expectedOverride = null)
    {
        $this->db       = $db;
        $this->expected = $expectedOverride ?? self::EXPECTED;
    }

    /** Returns 'proceed' or 'noop'; throws on any state this repair was not designed for. */
    public function assertPreconditions(): string
    {
        $sep = $this->sepTeamRows();
        $oct = $this->octPhantomTeamRows();

        if ($sep === 0 && $oct === 0) {
            return 'noop';
        }

        if ($sep + $oct !== $this->expected['phantom_team_rows']) {
            throw new RuntimeException(
                'Refusing to delete: found ' . $sep . ' Sep + ' . $oct
                . ' Oct phantom team rows, expected ' . $this->expected['phantom_team_rows'] . '.'
            );
        }

        if ($this->phantomPlayerRows() !== $this->expected['phantom_player_rows']) {
            throw new RuntimeException(
                'Refusing to delete: found ' . $this->phantomPlayerRows()
                . ' phantom player rows, expected ' . $this->expected['phantom_player_rows'] . '.'
            );
        }

        $surviving = $this->octTeamRows() - $oct;
        if ($surviving !== $this->expected['real_oct_team_rows']) {
            throw new RuntimeException(
                'Refusing to delete: the surviving Oct 2007 HEAT rows would be '
                . $surviving . ', expected ' . $this->expected['real_oct_team_rows'] . '.'
            );
        }

        $recaps = $this->phantomRecapRows();
        if ($recaps !== 0) {
            throw new RuntimeException(
                'Refusing to delete: ' . $recaps
                . ' sim game recap(s) reference a phantom coordinate.'
            );
        }

        return 'proceed';
    }

    /**
     * Back up and delete the phantoms — all inside one transaction.
     *
     * @return array{status: string, deleted: array{teams: int, players: int}}
     */
    public function runRepair(bool $dryRun): array
    {
        $status = $this->assertPreconditions();

        if ($status === 'noop') {
            return ['status' => 'noop', 'deleted' => ['teams' => 0, 'players' => 0]];
        }

        $bystandersBefore = $this->bystanderTeamRows();

        $this->db->begin_transaction();

        try {
            $this->fillBackups();
            $this->assertBackupCounts();

            $deleted = $this->deletePhantom();

            $this->assertPostDelete($bystandersBefore);

            if ($dryRun) {
                $this->db->rollback();
            } else {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        return ['status' => 'proceed', 'deleted' => $deleted];
    }

    // ---------------------------------------------------------------- reads

    private function sepTeamRows(): int
    {
        return $this->scalar(
            "SELECT COUNT(*) FROM `ibl_box_scores_teams` t
             WHERE t.game_date BETWEEN '2007-09-01' AND '2007-09-30'"
        );
    }

    private function octPhantomTeamRows(): int
    {
        return $this->scalar(
            'SELECT COUNT(*) FROM `ibl_box_scores_teams` t
             WHERE t.game_date BETWEEN \'2007-10-01\' AND \'2007-10-31\'
               AND ' . self::OCT_TWIN_EXISTS
        );
    }

    private function octTeamRows(): int
    {
        return $this->scalar(
            "SELECT COUNT(*) FROM `ibl_box_scores_teams` t
             WHERE t.game_date BETWEEN '2007-10-01' AND '2007-10-31'"
        );
    }

    private function phantomPlayerRows(): int
    {
        return $this->scalar(
            'SELECT COUNT(*) FROM `ibl_box_scores` p
             WHERE ' . self::PHANTOM_PLAYER_WHERE
        );
    }

    private function bystanderTeamRows(): int
    {
        return $this->scalar(
            "SELECT COUNT(*) FROM `ibl_box_scores_teams` t
             WHERE t.game_date BETWEEN '2007-11-01' AND '2007-12-31'"
        );
    }

    private function phantomRecapRows(): int
    {
        return $this->scalar(
            'SELECT COUNT(*) FROM ' . self::RECAP_TABLE . ' c
             WHERE EXISTS (
                 SELECT 1 FROM `ibl_box_scores_teams` t
                 WHERE t.game_date     = c.game_date
                   AND t.visitor_teamid = c.visitor_teamid
                   AND t.home_teamid    = c.home_teamid
                   AND ' . self::PHANTOM_TEAM_WHERE . '
             )'
        );
    }

    private function countBackup(string $table): int
    {
        $safeTable = match ($table) {
            self::TEAM_BACKUP_TABLE   => self::TEAM_BACKUP_TABLE,
            self::PLAYER_BACKUP_TABLE => self::PLAYER_BACKUP_TABLE,
            default => throw new \InvalidArgumentException('Unknown backup table: ' . $table),
        };

        return $this->scalar(
            "SELECT COUNT(*) FROM " . $safeTable . "
             WHERE game_date BETWEEN '" . self::SEP_START . "' AND '" . self::OCT_END . "'"
        );
    }

    // --------------------------------------------------------------- writes

    private function fillBackups(): void
    {
        $this->exec(
            'INSERT INTO ' . self::TEAM_BACKUP_TABLE . '
             SELECT t.* FROM ' . self::TEAM_TABLE . ' t
             WHERE ' . self::PHANTOM_TEAM_WHERE . '
               AND NOT EXISTS (SELECT 1 FROM ' . self::TEAM_BACKUP_TABLE . ' b WHERE b.id = t.id)'
        );

        $this->exec(
            'INSERT INTO ' . self::PLAYER_BACKUP_TABLE . '
             SELECT p.* FROM ' . self::PLAYER_TABLE . ' p
             WHERE ' . self::PHANTOM_PLAYER_WHERE . '
               AND NOT EXISTS (SELECT 1 FROM ' . self::PLAYER_BACKUP_TABLE . ' b WHERE b.id = p.id)'
        );
    }

    private function assertBackupCounts(): void
    {
        $teams   = $this->countBackup(self::TEAM_BACKUP_TABLE);
        $players = $this->countBackup(self::PLAYER_BACKUP_TABLE);

        if ($teams !== $this->expected['phantom_team_rows'] || $players !== $this->expected['phantom_player_rows']) {
            throw new RuntimeException(
                'Backup verification failed before delete: backed up ' . $teams
                . ' team rows and ' . $players . ' player rows, expected '
                . $this->expected['phantom_team_rows'] . ' and '
                . $this->expected['phantom_player_rows'] . '.'
            );
        }
    }

    /**
     * Delete players first (their Oct predicate reads live team rows), then teams
     * via a materialized subquery to avoid MariaDB ERROR 1093.
     *
     * @return array{teams: int, players: int}
     */
    private function deletePhantom(): array
    {
        $players = $this->exec(
            'DELETE p FROM ' . self::PLAYER_TABLE . ' p
             WHERE ' . self::PHANTOM_PLAYER_WHERE
        );

        if ($players !== $this->expected['phantom_player_rows']) {
            throw new RuntimeException(
                'Aborting: phantom player delete removed ' . $players
                . ' rows, expected ' . $this->expected['phantom_player_rows'] . '.'
            );
        }

        $teams = $this->exec(
            'DELETE t FROM ' . self::TEAM_TABLE . ' t
             JOIN (SELECT t.id FROM ' . self::TEAM_TABLE . ' t WHERE ' . self::PHANTOM_TEAM_WHERE . ') AS ids
               ON ids.id = t.id'
        );

        if ($teams !== $this->expected['phantom_team_rows']) {
            throw new RuntimeException(
                'Aborting: phantom team delete removed ' . $teams
                . ' rows, expected ' . $this->expected['phantom_team_rows'] . '.'
            );
        }

        return ['teams' => $teams, 'players' => $players];
    }

    private function assertPostDelete(int $bystandersBefore): void
    {
        if ($this->sepTeamRows() !== 0) {
            throw new RuntimeException('Post-delete check failed: Sep 2007 team rows still present.');
        }
        if ($this->octPhantomTeamRows() !== 0) {
            throw new RuntimeException('Post-delete check failed: Oct 2007 phantom team rows still present.');
        }
        if ($this->octTeamRows() !== $this->expected['real_oct_team_rows']) {
            throw new RuntimeException(
                'Post-delete check failed: Oct 2007 team rows = '
                . $this->octTeamRows() . ', expected ' . $this->expected['real_oct_team_rows'] . '.'
            );
        }
        if ($this->phantomPlayerRows() !== 0) {
            throw new RuntimeException('Post-delete check failed: phantom player rows still present.');
        }
        if ($this->bystanderTeamRows() !== $bystandersBefore) {
            throw new RuntimeException(
                'Post-delete check failed: bystander team rows changed from '
                . $bystandersBefore . ' to ' . $this->bystanderTeamRows() . '.'
            );
        }
    }

    // ------------------------------------------------------------- plumbing

    private function scalar(string $sql): int
    {
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare query: ' . $this->db->error);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            throw new RuntimeException('Failed to get result: ' . $this->db->error);
        }
        /** @var list<array<int, string|null>> $allRows */
        $allRows = $result->fetch_all(MYSQLI_NUM);
        $stmt->close();

        return (int) ($allRows[0][0] ?? 0);
    }

    private function exec(string $sql): int
    {
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare statement: ' . $this->db->error);
        }
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return (int) $affected;
    }
}
