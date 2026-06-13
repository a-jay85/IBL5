<?php

declare(strict_types=1);

namespace Watchlist;

use BaseMysqliRepository;
use Watchlist\Contracts\WatchlistRepositoryInterface;

/**
 * @see WatchlistRepositoryInterface
 * @phpstan-import-type WatchlistRow from \Watchlist\Contracts\WatchlistRepositoryInterface
 */
class WatchlistRepository extends BaseMysqliRepository implements WatchlistRepositoryInterface
{
    /**
     * @param \mysqli $db Active mysqli connection
     */
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    protected function rewriteTableNames(string $query): string
    {
        // The watchlist is a per-GM/franchise identity feature scoped to the IBL
        // (gm_username → teamid). It must never route to Olympics tables, so the
        // backtick-quoted ibl_plr/ibl_team_info references (required by the
        // bareTableIdentifier rule) are deliberately NOT rewritten. Mirrors
        // TeamIdentityRepository's identity-table override.
        return $query;
    }

    /**
     * @see WatchlistRepositoryInterface::isWatched()
     */
    public function isWatched(int $teamid, int $pid): bool
    {
        $row = $this->fetchOne(
            "SELECT 1 FROM `gm_player_watchlist` WHERE teamid = ? AND pid = ? LIMIT 1",
            "ii",
            $teamid,
            $pid
        );

        return $row !== null;
    }

    /**
     * @see WatchlistRepositoryInterface::addWatch()
     */
    public function addWatch(int $teamid, int $pid): bool
    {
        try {
            // INSERT IGNORE against the composite PK makes a re-watch a no-op.
            $this->execute(
                "INSERT IGNORE INTO `gm_player_watchlist` (teamid, pid) VALUES (?, ?)",
                "ii",
                $teamid,
                $pid
            );
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * @see WatchlistRepositoryInterface::removeWatch()
     */
    public function removeWatch(int $teamid, int $pid): bool
    {
        try {
            $this->execute(
                "DELETE FROM `gm_player_watchlist` WHERE teamid = ? AND pid = ?",
                "ii",
                $teamid,
                $pid
            );
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * @see WatchlistRepositoryInterface::saveNote()
     */
    public function saveNote(int $teamid, int $pid, string $note): bool
    {
        try {
            // WHERE teamid scoping means a foreign GM's write affects 0 rows.
            $this->execute(
                "UPDATE `gm_player_watchlist` SET note = ? WHERE teamid = ? AND pid = ?",
                "sii",
                $note,
                $teamid,
                $pid
            );
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * @see WatchlistRepositoryInterface::getWatchlistForTeam()
     *
     * @return list<WatchlistRow>
     */
    public function getWatchlistForTeam(int $teamid): array
    {
        /** @var list<WatchlistRow> */
        return $this->fetchAll(
            "SELECT w.pid, w.note, w.created_at, p.name, p.pos, p.teamid AS player_teamid,
                    t.team_name, p.stats_gm, p.stats_min, p.stats_fgm, p.stats_fga,
                    p.stats_ftm, p.stats_fta, p.stats_3gm, p.stats_orb, p.stats_drb,
                    p.stats_ast, p.stats_stl, p.stats_blk, p.stats_tvr, p.stats_pf
             FROM `gm_player_watchlist` w
             JOIN `ibl_plr` p ON p.pid = w.pid
             LEFT JOIN `ibl_team_info` t ON t.teamid = p.teamid
             WHERE w.teamid = ?
             ORDER BY w.created_at DESC",
            "i",
            $teamid
        );
    }
}
