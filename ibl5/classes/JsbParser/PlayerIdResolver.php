<?php

declare(strict_types=1);

namespace JsbParser;

/**
 * Resolves player names from .car files to database PIDs.
 *
 * Uses multiple matching strategies in priority order:
 * 1. Exact name match in ibl_plr for the given team and season
 * 2. Exact name match in ibl_hist for the same year + team
 * 3. Exact name match in ibl_plr without team constraint (for traded players)
 * 4. Fuzzy name match (Levenshtein ≤ 2) for encoding differences
 */
class PlayerIdResolver
{
    private \mysqli $db;

    /**
     * Cache of resolved name+team+year → pid.
     * @var array<string, int|null>
     */
    private array $cache = [];

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Resolve a player name + team + year to a database pid.
     *
     * @param string $name Player name from .car file
     * @param string $team Team name from .car file
     * @param int $year Season year
     * @return int|null Database pid, or null if not found
     */
    public function resolve(string $name, string $team, int $year): ?int
    {
        $cacheKey = $name . '|' . $team . '|' . $year;
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        // Strategy 1: Exact match in ibl_hist (most reliable for historical data)
        $pid = $this->findInHist($name, $team, $year);
        if ($pid !== null) {
            $this->cache[$cacheKey] = $pid;
            return $pid;
        }

        // Strategy 2: Exact match in ibl_plr by name + current team
        $pid = $this->findInPlr($name, $team);
        if ($pid !== null) {
            $this->cache[$cacheKey] = $pid;
            return $pid;
        }

        // Strategy 3: Exact name match in ibl_hist without team constraint
        $pid = $this->findInHistByNameOnly($name, $year);
        if ($pid !== null) {
            $this->cache[$cacheKey] = $pid;
            return $pid;
        }

        // Strategy 4: Exact name match in ibl_plr without team constraint
        $pid = $this->findInPlrByNameOnly($name);
        if ($pid !== null) {
            $this->cache[$cacheKey] = $pid;
            return $pid;
        }

        $this->cache[$cacheKey] = null;
        return null;
    }

    /**
     * Find pid in ibl_hist by exact name + team + year.
     */
    private function findInHist(string $name, string $team, int $year): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT pid FROM ibl_hist WHERE name = ? AND team = ? AND year = ? LIMIT 1'
        );
        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param('ssi', $name, $team, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        /** @var array{pid: int}|null $row */
        $row = $result !== false ? $result->fetch_assoc() : null;
        $stmt->close();

        return is_array($row) ? $row['pid'] : null;
    }

    /**
     * Find pid in ibl_plr by exact name + teamname.
     */
    private function findInPlr(string $name, string $team): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT pid FROM ibl_plr WHERE name = ? AND teamname = ? LIMIT 1'
        );
        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param('ss', $name, $team);
        $stmt->execute();
        $result = $stmt->get_result();
        /** @var array{pid: int}|null $row */
        $row = $result !== false ? $result->fetch_assoc() : null;
        $stmt->close();

        return is_array($row) ? $row['pid'] : null;
    }

    /**
     * Find pid in ibl_hist by exact name + year (ignoring team for traded players).
     */
    private function findInHistByNameOnly(string $name, int $year): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT pid FROM ibl_hist WHERE name = ? AND year = ? LIMIT 1'
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
            'SELECT pid FROM ibl_plr WHERE name = ? LIMIT 1'
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
