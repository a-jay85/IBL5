<?php

declare(strict_types=1);

namespace PlayerDatabase;

use BaseMysqliRepository;
use PlayerDatabase\Contracts\PlayerDatabaseRepositoryInterface;

/**
 * PlayerDatabaseRepository - Database operations for player search
 * 
 * Extends BaseMysqliRepository for standardized prepared statement handling.
 * Implements the repository contract defined in PlayerDatabaseRepositoryInterface.
 * See the interface for detailed behavior documentation.
 * 
 * @see BaseMysqliRepository For base class documentation and error codes
 * @see PlayerDatabaseRepositoryInterface For method contracts
 */
class PlayerDatabaseRepository extends BaseMysqliRepository implements PlayerDatabaseRepositoryInterface
{
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

    /**
     * Constructor - inherits from BaseMysqliRepository
     * 
     * @param \mysqli $db Active mysqli connection
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    /**
     * @see PlayerDatabaseRepositoryInterface::searchPlayers()
     */
    public function searchPlayers(array $params): array
    {
        $conditions = ['ibl_plr.pid > 0'];
        $bindParams = [];
        $bindTypes = '';

        if ($params['active'] === 0) {
            $conditions[] = 'ibl_plr.retired = 0';
        }

        if ($params['search_name'] !== null) {
            $conditions[] = 'ibl_plr.name LIKE ?';
            $bindParams[] = '%' . $params['search_name'] . '%';
            $bindTypes .= 's';
        }

        if ($params['college'] !== null) {
            $conditions[] = 'ibl_plr.college LIKE ?';
            $bindParams[] = '%' . $params['college'] . '%';
            $bindTypes .= 's';
        }

        if ($params['pos'] !== null) {
            $conditions[] = 'ibl_plr.pos = ?';
            $bindParams[] = $params['pos'];
            $bindTypes .= 's';
        }

        if ($params['age'] !== null) {
            $conditions[] = 'ibl_plr.age <= ?';
            $bindParams[] = $params['age'];
            $bindTypes .= 'i';
        }

        if ($params['exp'] !== null) {
            $conditions[] = 'ibl_plr.exp >= ?';
            $bindParams[] = $params['exp'];
            $bindTypes .= 'i';
        }
        if ($params['exp_max'] !== null) {
            $conditions[] = 'ibl_plr.exp <= ?';
            $bindParams[] = $params['exp_max'];
            $bindTypes .= 'i';
        }

        if ($params['bird'] !== null) {
            $conditions[] = 'ibl_plr.bird >= ?';
            $bindParams[] = $params['bird'];
            $bindTypes .= 'i';
        }
        if ($params['bird_max'] !== null) {
            $conditions[] = 'ibl_plr.bird <= ?';
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
                $conditions[] = "ibl_plr.$column >= ?";
                $bindParams[] = $params[$filter];
                $bindTypes .= 'i';
            }
        }

        $whereClause = implode(' AND ', $conditions);
        $query = "SELECT ibl_plr.*, ibl_team_info.color1, ibl_team_info.color2
            FROM ibl_plr
            LEFT JOIN ibl_team_info ON ibl_plr.tid = ibl_team_info.teamid
            WHERE $whereClause
            ORDER BY ibl_plr.retired ASC, ibl_plr.ordinal ASC";

        // Use executeQuery from BaseMysqliRepository for dynamic parameter binding
        // executeQuery handles prepare, bind_param, execute, and error logging
        $stmt = $this->executeQuery($query, $bindTypes, ...$bindParams);
        $result = $stmt->get_result();

        if ($result === false) {
            $stmt->close();
            throw new \RuntimeException('Failed to get search results');
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
     * @see PlayerDatabaseRepositoryInterface::getPlayerById()
     */
    public function getPlayerById(int $pid): ?array
    {
        // Use fetchOne from BaseMysqliRepository for single-row queries
        return $this->fetchOne(
            "SELECT * FROM ibl_plr WHERE pid = ?",
            "i",
            $pid
        );
    }
}
