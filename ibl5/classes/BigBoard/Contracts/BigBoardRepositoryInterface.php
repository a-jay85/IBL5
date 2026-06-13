<?php

declare(strict_types=1);

namespace BigBoard\Contracts;

/**
 * BigBoardRepositoryInterface - Contract for per-GM draft big-board data access.
 *
 * Every method is scoped to a server-resolved integer $teamid (never request
 * data). The `WHERE teamid = ?` predicate on each read and write is the IDOR
 * defense: a GM can only ever see or mutate rows owned by their own franchise.
 *
 * @phpstan-type BigBoardRow array{
 *     id: int,
 *     prospect_id: int,
 *     rank: int,
 *     note: string,
 *     name: string,
 *     pos: string,
 *     drafted: int
 * }
 * @phpstan-type AddableProspect array{
 *     id: int,
 *     name: string,
 *     pos: string
 * }
 */
interface BigBoardRepositoryInterface
{
    /**
     * All board entries for the team, joined to ibl_draft_class for name/pos/drafted,
     * ordered rank ASC then id ASC (deterministic tiebreak). Scoped by teamid.
     *
     * @return list<BigBoardRow>
     */
    public function getBoardForTeam(int $teamid): array;

    /**
     * Board entries for the team that are still available (draft_class.drafted = 0),
     * same ordering. Feeds the mock draft. Scoped by teamid.
     *
     * @return list<BigBoardRow>
     */
    public function getAvailableProspects(int $teamid): array;

    /**
     * Undrafted prospects NOT already on this team's board (the add candidates).
     * Scoped by teamid via the NOT IN subquery.
     *
     * @return list<AddableProspect>
     */
    public function getAddableProspects(int $teamid): array;

    /**
     * Add a prospect to the team's board. Plain INSERT: a duplicate
     * (teamid, prospect_id) trips the UNIQUE key and returns false (the service
     * maps that to a user error — re-adding is NOT a silent no-op).
     *
     * @return bool True if the row was inserted; false on duplicate / failure.
     */
    public function addEntry(int $teamid, int $prospectId, int $rank, string $note): bool;

    /**
     * Set the rank for one board entry. The `AND teamid = ?` scope is the IDOR
     * guard — a forged entry id owned by another team affects 0 rows.
     *
     * @return int Affected rows (0 when the entry is not owned by this team).
     */
    public function setRank(int $teamid, int $entryId, int $rank): int;

    /**
     * Set the note for one board entry. Same `AND teamid = ?` IDOR scope.
     *
     * @return int Affected rows (0 when the entry is not owned by this team).
     */
    public function setNote(int $teamid, int $entryId, string $note): int;

    /**
     * Remove one board entry. Same `AND teamid = ?` IDOR scope.
     *
     * @return int Affected rows (0 when the entry is not owned by this team).
     */
    public function removeEntry(int $teamid, int $entryId): int;
}
