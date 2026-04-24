<?php

declare(strict_types=1);

namespace PlayerDatabase;

use BaseMysqliRepository;
use PlayerDatabase\Contracts\PlayerDatabaseRepositoryInterface;
use Services\QueryConditions;

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
        'r_3ga' => 'r_3ga',
        'r_3gp' => 'r_3gp',
        'r_orb' => 'r_orb',
        'r_drb' => 'r_drb',
        'r_ast' => 'r_ast',
        'r_stl' => 'r_stl',
        'r_blk' => 'r_blk',
        // Filter keys match the form-field names (legacy user-facing API);
        // values are the post-rename DB column names.
        'r_to' => 'r_tvr',
        'r_foul' => 'r_foul',
        'Clutch' => 'clutch',
        'Consistency' => 'consistency',
        'talent' => 'talent',
        'skill' => 'skill',
        'intangibles' => 'intangibles',
        'oo' => 'oo',
        'do' => 'r_drive_off',
        'po' => 'po',
        'to' => 'r_trans_off',
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
        $baseConditions = ['ibl_plr.pid > 0'];
        if ($params['active'] === 0) {
            $baseConditions[] = 'ibl_plr.retired = 0';
        }
        $qc = new QueryConditions($baseConditions);

        // String filters (LIKE)
        $nameSearch = is_string($params['search_name']) ? '%' . $params['search_name'] . '%' : null;
        $qc->addIfNotNull('ibl_plr.name LIKE ?', 's', $nameSearch);

        $collegeSearch = is_string($params['college']) ? '%' . $params['college'] . '%' : null;
        $qc->addIfNotNull('ibl_plr.college LIKE ?', 's', $collegeSearch);

        $qc->addIfNotNull('ibl_plr.pos = ?', 's', is_string($params['pos']) ? $params['pos'] : null);

        // Integer filters (range)
        $qc->addIfNotNull('ibl_plr.age <= ?', 'i', is_int($params['age']) ? $params['age'] : null);
        $qc->addIfNotNull('ibl_plr.exp >= ?', 'i', is_int($params['exp']) ? $params['exp'] : null);
        $qc->addIfNotNull('ibl_plr.exp <= ?', 'i', is_int($params['exp_max']) ? $params['exp_max'] : null);
        $qc->addIfNotNull('ibl_plr.bird >= ?', 'i', is_int($params['bird']) ? $params['bird'] : null);
        $qc->addIfNotNull('ibl_plr.bird <= ?', 'i', is_int($params['bird_max']) ? $params['bird_max'] : null);

        // Rating filters (>= threshold)
        $greaterThanFilters = [
            'Clutch', 'Consistency', 'talent', 'skill', 'intangibles',
            'oo', 'do', 'po', 'to', 'od', 'dd', 'pd', 'td',
            'r_fga', 'r_fgp', 'r_fta', 'r_ftp', 'r_3ga', 'r_3gp',
            'r_orb', 'r_drb', 'r_ast', 'r_stl', 'r_blk', 'r_to', 'r_foul'
        ];

        foreach ($greaterThanFilters as $filter) {
            $filterValue = $params[$filter] ?? null;
            if (is_int($filterValue)) {
                $column = self::COLUMN_MAP[$filter];
                $qc->add("ibl_plr.$column >= ?", 'i', $filterValue);
            }
        }

        $whereClause = $qc->toWhereClause();
        $query = "SELECT ibl_plr.*, ibl_team_info.team_name AS teamname, ibl_team_info.color1, ibl_team_info.color2
            FROM ibl_plr
            LEFT JOIN ibl_team_info ON ibl_plr.teamid = ibl_team_info.teamid
            WHERE $whereClause
            ORDER BY ibl_plr.retired ASC, ibl_plr.ordinal ASC";

        $stmt = $this->executeQuery($query, $qc->getTypes(), ...$qc->getParams());
        $result = $stmt->get_result();

        if ($result === false) {
            $stmt->close();
            throw new \RuntimeException('Failed to get search results');
        }

        $players = [];
        while (true) {
            $row = $result->fetch_assoc();
            if (!is_array($row)) {
                break;
            }
            $players[] = $row;
        }

        $stmt->close();

        return [
            'results' => $players,
            'count' => count($players)
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
