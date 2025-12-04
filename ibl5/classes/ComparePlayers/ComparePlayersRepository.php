<?php

declare(strict_types=1);

namespace ComparePlayers;

use ComparePlayers\Contracts\ComparePlayersRepositoryInterface;

/**
 * @see ComparePlayersRepositoryInterface
 */
class ComparePlayersRepository implements ComparePlayersRepositoryInterface
{
    private object $db;

    public function __construct(object $db)
    {
        $this->db = $db;
    }

    /**
     * @see ComparePlayersRepositoryInterface::getAllPlayerNames()
     */
    public function getAllPlayerNames(): array
    {
        $query = "SELECT name FROM ibl_plr WHERE ordinal != 0 ORDER BY name ASC";

        if (method_exists($this->db, 'sql_query')) {
            // LEGACY: sql_* methods
            $result = $this->db->sql_query($query);
            $numRows = $this->db->sql_numrows($result);
            
            $names = [];
            for ($i = 0; $i < $numRows; $i++) {
                $names[] = $this->db->sql_result($result, $i, 'name');
            }
            return $names;
        } else {
            // MODERN: prepared statements (mysqli)
            $result = $this->db->query($query);
            if (!$result) {
                return [];
            }
            
            $names = [];
            while ($row = $result->fetch_assoc()) {
                $names[] = $row['name'];
            }
            return $names;
        }
    }

    /**
     * @see ComparePlayersRepositoryInterface::getPlayerByName()
     */
    public function getPlayerByName(string $playerName): ?array
    {
        if (method_exists($this->db, 'sql_query')) {
            // LEGACY: sql_* methods with escaping
            $escaped = \Services\DatabaseService::escapeString($this->db, $playerName);
            $query = "SELECT * FROM ibl_plr WHERE name = '$escaped' LIMIT 1";
            $result = $this->db->sql_query($query);
            
            if ($this->db->sql_numrows($result) === 0) {
                return null;
            }
            
            return $this->db->sql_fetch_assoc($result);
        } else {
            // MODERN: prepared statements (mysqli)
            $query = "SELECT * FROM ibl_plr WHERE name = ? LIMIT 1";
            $stmt = $this->db->prepare($query);
            
            if (!$stmt) {
                return null;
            }
            
            $stmt->bind_param('s', $playerName);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return null;
            }
            
            return $result->fetch_assoc();
        }
    }
}
