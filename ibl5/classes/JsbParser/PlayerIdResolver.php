<?php

declare(strict_types=1);

namespace JsbParser;

use League\LeagueContext;

/**
 * Resolves player names from .car files to database PIDs.
 *
 * Uses multiple matching strategies in priority order:
 * 1. Exact name match in ibl_plr_snapshots for the given team and season
 * 2. Exact name match in ibl_plr by name + team ID
 * 3. Exact name match in ibl_plr_snapshots for the same year (ignoring team, for traded players)
 * 4. Exact name match in ibl_plr by name only
 *
 * League-aware: resolves table names through LeagueContext when provided.
 */
class PlayerIdResolver
{
    private \mysqli $db;
    /** Player table per league; values pinned to LeagueContext::TABLE_MAP by PlayerIdResolverTest. */
    private const PLR_TABLES = [
        'ibl' => 'ibl_plr',
        'olympics' => 'ibl_olympics_plr',
    ];

    /** Snapshot table per league; values pinned to LeagueContext::TABLE_MAP by PlayerIdResolverTest. */
    private const SNAPSHOT_TABLES = [
        'ibl' => 'ibl_plr_snapshots',
        'olympics' => 'ibl_olympics_plr_snapshots',
    ];

    private bool $isOlympics;

    /**
     * Cache of resolved name+team+year → pid.
     * @var array<string, int|null>
     */
    private array $cache = [];

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        $this->db = $db;
        $this->isOlympics = $leagueContext !== null && $leagueContext->isOlympics();
    }

    /** @return value-of<self::PLR_TABLES> */
    private function plrTable(): string
    {
        return self::PLR_TABLES[$this->isOlympics ? 'olympics' : 'ibl'];
    }

    /** @return value-of<self::SNAPSHOT_TABLES> */
    private function snapshotTable(): string
    {
        return self::SNAPSHOT_TABLES[$this->isOlympics ? 'olympics' : 'ibl'];
    }

    /**
     * Resolve a player name + team + year to a database pid.
     *
     * @param string $name Player name from .car file
     * @param string $team Team name from .car file
     * @param int $year Season year
     * @param int|null $teamId Resolved team ID (when available, enables teamid-based ibl_plr lookup)
     * @return int|null Database pid, or null if not found
     */
    public function resolve(string $name, string $team, int $year, ?int $teamId = null): ?int
    {
        $cacheKey = $name . '|' . $team . '|' . $year;
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        // Strategy 1: Exact match in ibl_plr_snapshots by name + teamid + year
        if ($teamId !== null) {
            $pid = $this->findInSnapshots($name, $teamId, $year);
            if ($pid !== null) {
                $this->cache[$cacheKey] = $pid;
                return $pid;
            }
        }

        // Strategy 2: Exact match in ibl_plr by name + team ID
        if ($teamId !== null) {
            $pid = $this->findInPlr($name, $teamId);
            if ($pid !== null) {
                $this->cache[$cacheKey] = $pid;
                return $pid;
            }
        }

        // Strategy 3: Exact name match in ibl_plr_snapshots by name + year (ignoring team)
        $pid = $this->findInSnapshotsByNameOnly($name, $year);
        if ($pid !== null) {
            $this->cache[$cacheKey] = $pid;
            return $pid;
        }

        // Strategy 4: Exact name match in ibl_plr by name only
        $pid = $this->findInPlrByNameOnly($name);
        if ($pid !== null) {
            $this->cache[$cacheKey] = $pid;
            return $pid;
        }

        $this->cache[$cacheKey] = null;
        return null;
    }

    /**
     * Find pid in ibl_plr_snapshots by exact name + teamid + year.
     */
    private function findInSnapshots(string $name, int $teamId, int $year): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT pid FROM " . $this->snapshotTable() . " WHERE name = ? AND teamid = ? AND season_year = ? LIMIT 1"
        );
        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param('sii', $name, $teamId, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        /** @var array{pid: int}|null $row */
        $row = $result !== false ? $result->fetch_assoc() : null;
        $stmt->close();

        return is_array($row) ? $row['pid'] : null;
    }

    /**
     * Find pid in ibl_plr by exact name + team ID.
     */
    private function findInPlr(string $name, int $teamId): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT pid FROM " . $this->plrTable() . " WHERE name = ? AND teamid = ? LIMIT 1"
        );
        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param('si', $name, $teamId);
        $stmt->execute();
        $result = $stmt->get_result();
        /** @var array{pid: int}|null $row */
        $row = $result !== false ? $result->fetch_assoc() : null;
        $stmt->close();

        return is_array($row) ? $row['pid'] : null;
    }

    /**
     * Find pid in ibl_plr_snapshots by exact name + year (ignoring team for traded players).
     */
    private function findInSnapshotsByNameOnly(string $name, int $year): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT pid FROM " . $this->snapshotTable() . " WHERE name = ? AND season_year = ? LIMIT 1"
        );
        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param('si', $name, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        /** @var array{pid: int}|null $row */
        $row = $result !== false ? $result->fetch_assoc() : null;
        $stmt->close();

        return is_array($row) ? $row['pid'] : null;
    }

    /**
     * Find pid in ibl_plr by exact name only.
     */
    private function findInPlrByNameOnly(string $name): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT pid FROM " . $this->plrTable() . " WHERE name = ? LIMIT 1"
        );
        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param('s', $name);
        $stmt->execute();
        $result = $stmt->get_result();
        /** @var array{pid: int}|null $row */
        $row = $result !== false ? $result->fetch_assoc() : null;
        $stmt->close();

        return is_array($row) ? $row['pid'] : null;
    }

    /**
     * Clear the internal resolution cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
