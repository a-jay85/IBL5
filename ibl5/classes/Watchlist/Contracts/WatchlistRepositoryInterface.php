<?php

declare(strict_types=1);

namespace Watchlist\Contracts;

/**
 * WatchlistRepositoryInterface - Contract for per-GM watchlist data access.
 *
 * Every method is scoped to a server-resolved integer $teamid (never request
 * data). The `WHERE teamid = ?` predicate on each read and write is the IDOR
 * defense: a GM can only ever see or mutate rows owned by their own franchise.
 *
 * @phpstan-type WatchlistRow array{
 *     pid: int,
 *     note: ?string,
 *     created_at: string,
 *     name: ?string,
 *     pos: ?string,
 *     player_teamid: ?int,
 *     team_name: ?string,
 *     stats_gm: int|string|null,
 *     stats_min: int|string|null,
 *     stats_fgm: int|string|null,
 *     stats_fga: int|string|null,
 *     stats_ftm: int|string|null,
 *     stats_fta: int|string|null,
 *     stats_3gm: int|string|null,
 *     stats_orb: int|string|null,
 *     stats_drb: int|string|null,
 *     stats_ast: int|string|null,
 *     stats_stl: int|string|null,
 *     stats_blk: int|string|null,
 *     stats_tvr: int|string|null,
 *     stats_pf: int|string|null
 * }
 */
interface WatchlistRepositoryInterface
{
    /**
     * Whether (teamid, pid) is currently on the team's watchlist.
     */
    public function isWatched(int $teamid, int $pid): bool;

    /**
     * Add a player to the team's watchlist. Idempotent (INSERT IGNORE against
     * the composite PK), so re-watching an already-watched player is a no-op.
     *
     * @return bool True if the statement executed without error.
     */
    public function addWatch(int $teamid, int $pid): bool;

    /**
     * Remove a player from the team's watchlist. Scoped by teamid.
     *
     * @return bool True if the statement executed without error.
     */
    public function removeWatch(int $teamid, int $pid): bool;

    /**
     * Persist the free-text note for an already-watched (teamid, pid). The
     * UPDATE's `WHERE teamid = ?` guarantees a foreign GM's write affects 0 rows.
     *
     * @return bool True if the statement executed without error.
     */
    public function saveNote(int $teamid, int $pid, string $note): bool;

    /**
     * All watched players for the team, joined to ibl_plr for name + quick stats,
     * ordered most-recently-watched first. Scoped by teamid (IDOR defense).
     *
     * @return list<WatchlistRow>
     */
    public function getWatchlistForTeam(int $teamid): array;
}
