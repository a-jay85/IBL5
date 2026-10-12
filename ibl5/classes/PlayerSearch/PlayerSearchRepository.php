<?php

declare(strict_types=1);

namespace PlayerSearch;

use Database\BaseMysqliRepository;
use PlayerSearch\Contracts\PlayerSearchRepositoryInterface;

/**
 * PlayerSearchRepository - Database operations for player search
 * 
 * Extends BaseMysqliRepository for standardized prepared statement handling.
 * Implements the repository contract defined in PlayerSearchRepositoryInterface.
 * See the interface for detailed behavior documentation.
 * 
 * @see BaseMysqliRepository For base class documentation and error codes
 * @see PlayerSearchRepositoryInterface For method contracts
 */
class PlayerSearchRepository extends BaseMysqliRepository implements PlayerSearchRepositoryInterface
{
    private const GREATER_THAN_FILTERS = [
        'Clutch', 'Consistency', 'talent', 'skill', 'intangibles',
        'oo', 'do', 'po', 'to', 'od', 'dd', 'pd', 'td',
        'r_fga', 'r_fgp', 'r_fta', 'r_ftp', 'r_3ga', 'r_3gp',
        'r_orb', 'r_drb', 'r_ast', 'r_stl', 'r_blk', 'r_to', 'r_foul'
    ];

    /**
     * Fully constant search WHERE. Every optional predicate is flag-guarded,
     * `(? = 0 OR <pred>)`, and searchPlayers() always binds a flag then a value for
     * each one (flag 0 + neutral value when the filter is absent). Placeholder order:
     * active flag, then (flag, value) for name, college, pos, age, exp, exp_max, bird,
     * bird_max, then (flag, value) for each GREATER_THAN_FILTERS entry in order. The
     * rating groups map form-field name to DB column (r_to -> r_tvr, do -> r_drive_off,
     * to -> r_trans_off, Clutch -> clutch, Consistency -> consistency; the rest are
     * identical).
     */
    private const SEARCH_WHERE = '`ibl_plr`.`pid` > 0 AND (? = 0 OR `ibl_plr`.`retired` = 0) AND (? = 0 OR `ibl_plr`.`name` LIKE ?) AND (? = 0 OR `ibl_plr`.`college` LIKE ?) AND (? = 0 OR `ibl_plr`.`pos` = ?) AND (? = 0 OR `ibl_plr`.`age` <= ?) AND (? = 0 OR `ibl_plr`.`exp` >= ?) AND (? = 0 OR `ibl_plr`.`exp` <= ?) AND (? = 0 OR `ibl_plr`.`bird` >= ?) AND (? = 0 OR `ibl_plr`.`bird` <= ?) AND '
        . '(? = 0 OR `ibl_plr`.`clutch` >= ?) AND (? = 0 OR `ibl_plr`.`consistency` >= ?) AND (? = 0 OR `ibl_plr`.`talent` >= ?) AND (? = 0 OR `ibl_plr`.`skill` >= ?) AND (? = 0 OR `ibl_plr`.`intangibles` >= ?) AND (? = 0 OR `ibl_plr`.`oo` >= ?) AND (? = 0 OR `ibl_plr`.`r_drive_off` >= ?) AND (? = 0 OR `ibl_plr`.`po` >= ?) AND (? = 0 OR `ibl_plr`.`r_trans_off` >= ?) AND (? = 0 OR `ibl_plr`.`od` >= ?) AND (? = 0 OR `ibl_plr`.`dd` >= ?) AND (? = 0 OR `ibl_plr`.`pd` >= ?) AND (? = 0 OR `ibl_plr`.`td` >= ?) AND (? = 0 OR `ibl_plr`.`r_fga` >= ?) AND (? = 0 OR `ibl_plr`.`r_fgp` >= ?) AND (? = 0 OR `ibl_plr`.`r_fta` >= ?) AND (? = 0 OR `ibl_plr`.`r_ftp` >= ?) AND (? = 0 OR `ibl_plr`.`r_3ga` >= ?) AND (? = 0 OR `ibl_plr`.`r_3gp` >= ?) AND (? = 0 OR `ibl_plr`.`r_orb` >= ?) AND (? = 0 OR `ibl_plr`.`r_drb` >= ?) AND (? = 0 OR `ibl_plr`.`r_ast` >= ?) AND (? = 0 OR `ibl_plr`.`r_stl` >= ?) AND (? = 0 OR `ibl_plr`.`r_blk` >= ?) AND (? = 0 OR `ibl_plr`.`r_tvr` >= ?) AND (? = 0 OR `ibl_plr`.`r_foul` >= ?)';

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
     * @see PlayerSearchRepositoryInterface::searchPlayers()
     */
    public function searchPlayers(array $params): array
    {
        $input = $params;

        $types = 'i';
        $bind = [$input['active'] === 0 ? 1 : 0];

        // String filters (LIKE / equality)
        $nameSearch = is_string($input['search_name']) ? '%' . $input['search_name'] . '%' : null;
        $types .= 'is';
        $bind[] = $nameSearch !== null ? 1 : 0;
        $bind[] = $nameSearch ?? '';

        $collegeSearch = is_string($input['college']) ? '%' . $input['college'] . '%' : null;
        $types .= 'is';
        $bind[] = $collegeSearch !== null ? 1 : 0;
        $bind[] = $collegeSearch ?? '';

        $pos = is_string($input['pos']) ? $input['pos'] : null;
        $types .= 'is';
        $bind[] = $pos !== null ? 1 : 0;
        $bind[] = $pos ?? '';

        // Integer filters (range): age <=, exp >=, exp <=, bird >=, bird <=
        $rangeValues = [
            is_int($input['age']) ? $input['age'] : null,
            is_int($input['exp']) ? $input['exp'] : null,
            is_int($input['exp_max']) ? $input['exp_max'] : null,
            is_int($input['bird']) ? $input['bird'] : null,
            is_int($input['bird_max']) ? $input['bird_max'] : null,
        ];
        foreach ($rangeValues as $rangeValue) {
            $types .= 'ii';
            $bind[] = $rangeValue !== null ? 1 : 0;
            $bind[] = $rangeValue ?? 0;
        }

        // Rating filters (>= threshold); columns are baked into SEARCH_WHERE
        foreach (self::GREATER_THAN_FILTERS as $filter) {
            $filterValue = $input[$filter] ?? null;
            $filterValue = is_int($filterValue) ? $filterValue : null;
            $types .= 'ii';
            $bind[] = $filterValue !== null ? 1 : 0;
            $bind[] = $filterValue ?? 0;
        }

        $query = "SELECT `ibl_plr`.*, `ibl_team_info`.`team_name` AS teamname, `ibl_team_info`.`color1`, `ibl_team_info`.`color2`
            FROM `ibl_plr`
            LEFT JOIN `ibl_team_info` ON `ibl_plr`.`teamid` = `ibl_team_info`.`teamid`
            WHERE " . self::SEARCH_WHERE . "
            ORDER BY `ibl_plr`.`retired` ASC, `ibl_plr`.`ordinal` ASC, `ibl_plr`.`pid` ASC";

        $stmt = $this->executeQuery($query, $types, ...$bind);
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
     * @see PlayerSearchRepositoryInterface::getPlayerById()
     */
    public function getPlayerById(int $pid): ?array
    {
        // Use fetchOne from BaseMysqliRepository for single-row queries
        return $this->fetchOne(
            "SELECT * FROM `ibl_plr` WHERE pid = ?",
            "i",
            $pid
        );
    }
}
