<?php

declare(strict_types=1);

namespace PlayerSearch;

use mysqli;
use PlayerSearch\Contracts\PlayerSearchRepositoryInterface;

/**
 * PlayerSearchRepository - Database operations for player search
 * 
 * Implements the repository contract defined in PlayerSearchRepositoryInterface.
 * See the interface for detailed behavior documentation.
 */
class PlayerSearchRepository implements PlayerSearchRepositoryInterface
{
    private mysqli $db;

    private const COLUMN_MAP = [
        'pos' => 'pos',
        'age' => 'age',
        'search_name' => 'name',
        'college' => 'college',
        'exp' => 'exp',
        'exp_max' => 'exp_max',
        'bird' => 'bird',
        'bird_max' => 'bird_max',
        'r_fga' => 'r_fga',
        'r_fgp' => 'r_fgp',
        'r_fta' => 'r_fta',
        'r_ftp' => 'r_ftp',
        'r_tga' => 'r_tga',
        'r_tgp' => 'r_tgp',
        'r_orb' => 'r_orb',
        'r_drb' => 'r_drb',
        'r_ast' => 'r_ast',
        'r_stl' => 'r_stl',
        'r_blk' => 'r_blk',
        'r_to' => 'r_to',
        'r_foul' => 'r_foul',
        'Clutch' => 'Clutch',
        'Consistency' => 'Consistency',
        'talent' => 'talent',
        'skill' => 'skill',
        'intangibles' => 'intangibles',
        'oo' => 'oo',
        'do' => '`do`',
        'po' => 'po',
        'to' => '`to`',
        'od' => 'od',
        'dd' => 'dd',
        'pd' => 'pd',
        'td' => 'td',
    ];

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * @see PlayerSearchRepositoryInterface::searchPlayers()
     */
    public function searchPlayers(array $params): array
    {
        $conditions = ['pid > 0'];
        $bindParams = [];
        $bindTypes = '';

        if ($params['active'] === 0) {
            $conditions[] = 'retired = 0';
        }

        if ($params['search_name'] !== null) {
            $conditions[] = 'name LIKE ?';
            $bindParams[] = '%' . $params['search_name'] . '%';
            $bindTypes .= 's';
        }

        if ($params['college'] !== null) {
            $conditions[] = 'college LIKE ?';
            $bindParams[] = '%' . $params['college'] . '%';
            $bindTypes .= 's';
        }

        if ($params['pos'] !== null) {
            $conditions[] = 'pos = ?';
            $bindParams[] = $params['pos'];
            $bindTypes .= 's';
        }

        if ($params['age'] !== null) {
            $conditions[] = 'age <= ?';
            $bindParams[] = $params['age'];
            $bindTypes .= 'i';
        }

        if ($params['exp'] !== null) {
            $conditions[] = 'exp >= ?';
            $bindParams[] = $params['exp'];
            $bindTypes .= 'i';
        }
        if ($params['exp_max'] !== null) {
            $conditions[] = 'exp <= ?';
            $bindParams[] = $params['exp_max'];
            $bindTypes .= 'i';
        }

        if ($params['bird'] !== null) {
            $conditions[] = 'bird >= ?';
            $bindParams[] = $params['bird'];
            $bindTypes .= 'i';
        }
        if ($params['bird_max'] !== null) {
            $conditions[] = 'bird <= ?';
            $bindParams[] = $params['bird_max'];
            $bindTypes .= 'i';
        }

        $greaterThanFilters = [
            'Clutch', 'Consistency', 'talent', 'skill', 'intangibles',
            'oo', 'do', 'po', 'to', 'od', 'dd', 'pd', 'td',
            'r_fga', 'r_fgp', 'r_fta', 'r_ftp', 'r_tga', 'r_tgp',
            'r_orb', 'r_drb', 'r_ast', 'r_stl', 'r_blk', 'r_to', 'r_foul'
        ];

        foreach ($greaterThanFilters as $filter) {
            if (isset($params[$filter]) && $params[$filter] !== null) {
                $column = self::COLUMN_MAP[$filter] ?? $filter;
                $conditions[] = "$column >= ?";
                $bindParams[] = $params[$filter];
                $bindTypes .= 'i';
            }
        }

        $whereClause = implode(' AND ', $conditions);
        $query = "SELECT * FROM ibl_plr WHERE $whereClause ORDER BY retired ASC, ordinal ASC";

        $stmt = $this->db->prepare($query);
        if ($stmt === false) {
            throw new \RuntimeException('Failed to prepare search query: ' . $this->db->error);
        }

        if (!empty($bindParams)) {
            $stmt->bind_param($bindTypes, ...$bindParams);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === false) {
            throw new \RuntimeException('Failed to execute search query: ' . $stmt->error);
        }

        $players = [];
        while ($row = $result->fetch_assoc()) {
            $players[] = $row;
        }

        $count = count($players);
        $stmt->close();

        return [
            'results' => $players,
            'count' => $count
        ];
    }

    /**
     * @see PlayerSearchRepositoryInterface::getPlayerById()
     */
    public function getPlayerById(int $pid): ?array
    {
        $query = "SELECT * FROM ibl_plr WHERE pid = ?";
        $stmt = $this->db->prepare($query);
        
        if ($stmt === false) {
            throw new \RuntimeException('Failed to prepare player query: ' . $this->db->error);
        }

        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === false) {
            throw new \RuntimeException('Failed to execute player query: ' . $stmt->error);
        }

        $player = $result->fetch_assoc();
        $stmt->close();

        return $player ?: null;
    }
}
