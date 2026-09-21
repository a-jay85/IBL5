<?php

declare(strict_types=1);

namespace Boxscore;

use mysqli;
use RuntimeException;

/**
 * One-shot repair for the phantom 1993-06-04 Warriors @ Supersonics boxscore.
 *
 * A 1993 playoff sim wrote the round-1 game 4 boxscore twice on the same date:
 * once at game_of_that_day = 1 (the phantom) and once at game_of_that_day = 5
 * (the real game). A (date, visitor, home) triple is unique in this league, so
 * the slot-1 copy is a duplicate, never a second game. This class backs up and
 * deletes the slot-1 rows only. It never touches slot 5. Driver: migration 181.
 * Backup tables: migration 180.
 */
final class Season1993PhantomRepair
{
    public const GAME_DATE = '1993-06-04';
    public const GAME_OF_THAT_DAY = 1;
    public const REAL_GAME_OF_THAT_DAY = 5;
    public const PHANTOM_VISITOR_TEAMID = 24;
    public const PHANTOM_HOME_TEAMID = 22;

    public const TEAM_TABLE = 'ibl_box_scores_teams';
    public const PLAYER_TABLE = 'ibl_box_scores';
    public const TEAM_BACKUP_TABLE = 'ibl_box_scores_teams_season1993_phantom_backup';
    public const PLAYER_BACKUP_TABLE = 'ibl_box_scores_season1993_phantom_backup';
    private const RECAP_TABLE = 'ibl_sim_game_recaps';

    /** Fingerprint captured from the live database while the plan was written. */
    private const EXPECTED = [
        'phantom_ids' => [11947, 11948],
        'phantom_player_rows' => 24,
        'phantom_player_id_range' => [140621, 140644],
    ];

    private mysqli $db;

    /** @var array{phantom_ids: list<int>, phantom_player_rows: int, phantom_player_id_range: array{int, int}} */
    private array $expected;

    /**
     * @param array{phantom_ids: list<int>, phantom_player_rows: int, phantom_player_id_range: array{int, int}}|null $expectedOverride
     *        Test-only seam: lets an integration fixture assert against its own seeded ids.
     */
    public function __construct(mysqli $db, ?array $expectedOverride = null)
    {
        $this->db = $db;
        $this->expected = $expectedOverride ?? self::EXPECTED;
    }

    /** Returns 'proceed' or 'noop'; throws on any state this repair was not designed for. */
    public function assertPreconditions(): string
    {
        $phantomIds = $this->phantomTeamRowIds();

        if ($phantomIds === []) {
            return 'noop';
        }

        $expectedIds = $this->expected['phantom_ids'];
        sort($expectedIds);
        if ($phantomIds !== $expectedIds) {
            throw new RuntimeException(
                'Refusing to delete: phantom team-row ids [' . implode(', ', $phantomIds)
                . '] do not match the ids this repair was designed against ['
                . implode(', ', $expectedIds) . ']. The database has changed since the '
                . 'repair was written.'
            );
        }

        $playerRows = $this->phantomPlayerRowCount();
        if ($playerRows !== $this->expected['phantom_player_rows']) {
            throw new RuntimeException(
                'Refusing to delete: found ' . $playerRows . ' phantom player rows, '
                . 'expected ' . $this->expected['phantom_player_rows'] . '.'
            );
        }

        $idRange = $this->phantomPlayerIdRange();
        $expectedRange = $this->expected['phantom_player_id_range'];
        if ($idRange !== $expectedRange) {
            throw new RuntimeException(
                'Refusing to delete: phantom player ids span ['
                . implode(', ', $idRange) . '], expected ['
                . implode(', ', $expectedRange) . '].'
            );
        }

        $realCount = $this->realGameTeamRowCount();
        if ($realCount !== 2) {
            throw new RuntimeException(
                'Refusing to delete: the real game at ordinal 5 is not present (found '
                . $realCount . ' team rows). Deleting ordinal 1 would erase the only copy.'
            );
        }

        $recaps = $this->phantomRecapCount();
        if ($recaps !== 0) {
            throw new RuntimeException(
                'Refusing to delete: ' . $recaps . ' sim game recap(s) reference the '
                . 'phantom coordinate. Deleting the boxscore would orphan them.'
            );
        }

        return 'proceed';
    }

    /**
     * Back up and delete the phantom -- all inside one transaction.
     *
     * @return array{status: string, deleted: array{teams: int, players: int}}
     */
    public function runRepair(bool $dryRun): array
    {
        $status = $this->assertPreconditions();

        if ($status === 'noop') {
            return ['status' => 'noop', 'deleted' => ['teams' => 0, 'players' => 0]];
        }

        $this->db->begin_transaction();

        try {
            $this->fillBackups();
            $this->assertBackupCounts();

            $deleted = $this->deletePhantom();

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

    /** @return list<int> ascending phantom team-row ids */
    private function phantomTeamRowIds(): array
    {
        $sql = 'SELECT id FROM ' . self::TEAM_TABLE . '
                WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
                  AND game_of_that_day = ?
                ORDER BY id';
        $rows = $this->selectScalars($sql, self::PHANTOM_VISITOR_TEAMID, self::PHANTOM_HOME_TEAMID);

        return array_map('intval', $rows);
    }

    private function phantomPlayerRowCount(): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . self::PLAYER_TABLE . '
                WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
                  AND game_of_that_day = ?';
        $rows = $this->selectScalars($sql, self::PHANTOM_VISITOR_TEAMID, self::PHANTOM_HOME_TEAMID);

        return (int) ($rows[0] ?? 0);
    }

    /** @return array{int, int} */
    private function phantomPlayerIdRange(): array
    {
        $stmt = $this->db->prepare(
            'SELECT MIN(id), MAX(id) FROM ' . self::PLAYER_TABLE . '
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
               AND game_of_that_day = ?'
        );
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare id-range query: ' . $this->db->error);
        }

        $date = self::GAME_DATE;
        $visitor = self::PHANTOM_VISITOR_TEAMID;
        $home = self::PHANTOM_HOME_TEAMID;
        $ordinal = self::GAME_OF_THAT_DAY;
        $stmt->bind_param('siii', $date, $visitor, $home, $ordinal);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            throw new RuntimeException('Failed to get id-range result: ' . $this->db->error);
        }
        /** @var list<list<string|null>> $allRows */
        $allRows = $result->fetch_all(MYSQLI_NUM);
        $stmt->close();
        $row = $allRows[0] ?? [];

        return [(int) ($row[0] ?? 0), (int) ($row[1] ?? 0)];
    }

    private function realGameTeamRowCount(): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . self::TEAM_TABLE . '
                WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
                  AND game_of_that_day = ?';
        $rows = $this->selectScalars(
            $sql,
            self::PHANTOM_VISITOR_TEAMID,
            self::PHANTOM_HOME_TEAMID,
            self::REAL_GAME_OF_THAT_DAY
        );

        return (int) ($rows[0] ?? 0);
    }

    private function phantomRecapCount(): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . self::RECAP_TABLE . '
                WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
                  AND game_of_that_day = ?';
        $rows = $this->selectScalars($sql, self::PHANTOM_VISITOR_TEAMID, self::PHANTOM_HOME_TEAMID);

        return (int) ($rows[0] ?? 0);
    }

    /**
     * Runs one of the four-placeholder coordinate queries above.
     *
     * @return list<string>
     */
    private function selectScalars(
        string $sql,
        int $visitorTeamid,
        int $homeTeamid,
        int $gameOfThatDay = self::GAME_OF_THAT_DAY
    ): array {
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare query: ' . $this->db->error);
        }

        $date = self::GAME_DATE;
        $ordinal = $gameOfThatDay;
        $stmt->bind_param('siii', $date, $visitorTeamid, $homeTeamid, $ordinal);
        $stmt->execute();

        $result = $stmt->get_result();
        if ($result === false) {
            throw new RuntimeException('Failed to get result set: ' . $this->db->error);
        }
        $values = [];
        /** @var list<array<int, string|null>> $allRows */
        $allRows = $result->fetch_all(MYSQLI_NUM);
        foreach ($allRows as $row) {
            $values[] = (string) ($row[0] ?? '');
        }
        $stmt->close();

        return $values;
    }

    // --------------------------------------------------------------- writes

    /** Copy the phantom rows into the season-1993 backup tables. */
    private function fillBackups(): void
    {
        $this->execCoordinate(
            'INSERT INTO ' . self::TEAM_BACKUP_TABLE . '
             SELECT live.* FROM ' . self::TEAM_TABLE . ' AS live
             WHERE live.game_date = ? AND live.visitor_teamid = ? AND live.home_teamid = ?
               AND live.game_of_that_day = ?
               AND NOT EXISTS (
                 SELECT 1 FROM ' . self::TEAM_BACKUP_TABLE . ' AS b WHERE b.id = live.id
               )',
            self::PHANTOM_VISITOR_TEAMID,
            self::PHANTOM_HOME_TEAMID
        );

        $this->execCoordinate(
            'INSERT INTO ' . self::PLAYER_BACKUP_TABLE . '
             SELECT live.* FROM ' . self::PLAYER_TABLE . ' AS live
             WHERE live.game_date = ? AND live.visitor_teamid = ? AND live.home_teamid = ?
               AND live.game_of_that_day = ?
               AND NOT EXISTS (
                 SELECT 1 FROM ' . self::PLAYER_BACKUP_TABLE . ' AS b WHERE b.id = live.id
               )',
            self::PHANTOM_VISITOR_TEAMID,
            self::PHANTOM_HOME_TEAMID
        );
    }

    /** The delete is irreversible without these; verify before running it. */
    private function assertBackupCounts(): void
    {
        $teams = $this->countBackup(self::TEAM_BACKUP_TABLE);
        $players = $this->countBackup(self::PLAYER_BACKUP_TABLE);

        $expectedTeams = count($this->expected['phantom_ids']);
        $expectedPlayers = $this->expected['phantom_player_rows'];

        if ($teams !== $expectedTeams || $players !== $expectedPlayers) {
            throw new RuntimeException(
                'Backup verification failed before delete: backed up ' . $teams
                . ' team rows and ' . $players . ' player rows, expected '
                . $expectedTeams . ' and ' . $expectedPlayers . '.'
            );
        }
    }

    private function countBackup(string $table): int
    {
        $safeTable = match($table) {
            self::TEAM_BACKUP_TABLE => self::TEAM_BACKUP_TABLE,
            self::PLAYER_BACKUP_TABLE => self::PLAYER_BACKUP_TABLE,
            default => throw new \InvalidArgumentException('Unknown backup table: ' . $table),
        };
        $sql = 'SELECT COUNT(*) FROM ' . $safeTable . '
                WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
                  AND game_of_that_day = ?';
        $rows = $this->selectScalars($sql, self::PHANTOM_VISITOR_TEAMID, self::PHANTOM_HOME_TEAMID);

        return (int) ($rows[0] ?? 0);
    }

    /**
     * Leaves first: player rows carry no FK to the team row, but deleting them
     * first keeps the intermediate state readable rather than half-orphaned.
     *
     * @return array{teams: int, players: int}
     */
    private function deletePhantom(): array
    {
        $players = $this->execCoordinate(
            'DELETE FROM ' . self::PLAYER_TABLE . '
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
               AND game_of_that_day = ?',
            self::PHANTOM_VISITOR_TEAMID,
            self::PHANTOM_HOME_TEAMID
        );

        if ($players !== $this->expected['phantom_player_rows']) {
            throw new RuntimeException(
                'Aborting: phantom player delete removed ' . $players . ' rows, expected '
                . $this->expected['phantom_player_rows'] . '.'
            );
        }

        $teams = $this->execCoordinate(
            'DELETE FROM ' . self::TEAM_TABLE . '
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
               AND game_of_that_day = ?',
            self::PHANTOM_VISITOR_TEAMID,
            self::PHANTOM_HOME_TEAMID
        );

        $expectedTeams = count($this->expected['phantom_ids']);
        if ($teams !== $expectedTeams) {
            throw new RuntimeException(
                'Aborting: phantom team delete removed ' . $teams . ' rows, expected '
                . $expectedTeams . '.'
            );
        }

        return ['teams' => $teams, 'players' => $players];
    }

    // ------------------------------------------------------------- plumbing

    /**
     * Runs a four-placeholder coordinate statement; returns affected rows.
     *
     * No bindAndExecute() helper here, though the plan's Phase 2 recipe named one.
     * This method, selectScalars() and phantomPlayerIdRange() each bind and execute
     * inline instead. The invariant that helper existed to protect still holds by
     * construction: every coordinate statement in this class binds the same four
     * columns in the same order -- game_date, visitor_teamid, home_teamid,
     * game_of_that_day -- so no query can address a coordinate wider than the
     * phantom. A shared helper would need a third signature anyway, since the
     * id-range query returns two columns where the scalar queries return one.
     * Backlog: a-jay85/IBL5-backlog#935.
     */
    private function execCoordinate(string $sql, int $visitorTeamid, int $homeTeamid): int
    {
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare statement: ' . $this->db->error);
        }

        $date = self::GAME_DATE;
        $ordinal = self::GAME_OF_THAT_DAY;
        $stmt->bind_param('siii', $date, $visitorTeamid, $homeTeamid, $ordinal);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return (int) $affected;
    }

}
