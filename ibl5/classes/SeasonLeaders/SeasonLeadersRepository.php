<?php

namespace SeasonLeaders;

use Services\DatabaseService;

/**
 * SeasonLeadersRepository - Handles all database operations for season leaders
 * 
 * Following the Repository pattern, this class encapsulates all SQL queries
 * and database interactions for historical season statistics.
 */
class SeasonLeadersRepository
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get season leaders based on filters
     * 
     * @param array $filters Associative array with 'year', 'team', 'sortBy' keys
     * @return array Query result resource and row count
     */
    public function getSeasonLeaders(array $filters): array
    {
        $conditions = ["name IS NOT NULL"];
        
        // Add year filter if specified
        if (!empty($filters['year'])) {
            $year = DatabaseService::escapeString($this->db, $filters['year']);
            $conditions[] = "year = '$year'";
        }
        
        // Add team filter if specified and not "All"
        if (!empty($filters['team']) && $filters['team'] != 0) {
            $teamId = (int)$filters['team'];
            $conditions[] = "teamid = $teamId";
        }
        
        $whereClause = implode(' AND ', $conditions);
        $sortBy = $this->getSortColumn($filters['sortBy'] ?? '1');
        
        $query = "SELECT * FROM ibl_hist WHERE $whereClause ORDER BY $sortBy DESC";
        $result = $this->db->sql_query($query);
        $numRows = $this->db->sql_numrows($result);
        
        return [
            'result' => $result,
            'count' => $numRows
        ];
    }

    /**
     * Get all teams for dropdown
     * 
     * @return array Query result resource
     */
    public function getTeams()
    {
        $query = "SELECT * FROM ibl_power WHERE TeamID BETWEEN 1 AND 32 ORDER BY TeamID ASC";
        return $this->db->sql_query($query);
    }

    /**
     * Get all distinct years from history
     * 
     * @return array Array of years
     */
    public function getYears(): array
    {
        $query = "SELECT DISTINCT year FROM ibl_hist ORDER BY year DESC";
        $result = $this->db->sql_query($query);
        $years = [];
        
        $i = 0;
        while ($i < $this->db->sql_numrows($result)) {
            $years[] = $this->db->sql_result($result, $i, 'year');
            $i++;
        }
        
        return $years;
    }

    /**
     * Map sort option to database column/expression
     * 
     * @param string $sortBy Sort option identifier
     * @return string SQL expression for sorting
     */
    private function getSortColumn(string $sortBy): string
    {
        $sortMap = [
            '1' => '((2*`fgm`+`ftm`+`tgm`)/`games`)', // PPG
            '2' => '((reb)/`games`)',                   // REB
            '3' => '((orb)/`games`)',                   // OREB
            '4' => '((ast)/`games`)',                   // AST
            '5' => '((stl)/`games`)',                   // STL
            '6' => '((blk)/`games`)',                   // BLK
            '7' => '((tvr)/`games`)',                   // TO
            '8' => '((pf)/`games`)',                    // FOUL
            '9' => '((((2*fgm+ftm+tgm)+reb+(2*ast)+(2*stl)+(2*blk))-((fga-fgm)+(fta-ftm)+tvr+pf))/games)', // QA
            '10' => '((fgm)/`games`)',                  // FGM
            '11' => '((fga)/`games`)',                  // FGA
            '12' => '(fgm/fga)',                        // FG%
            '13' => '((ftm)/`games`)',                  // FTM
            '14' => '((fta)/`games`)',                  // FTA
            '15' => '(ftm/fta)',                        // FT%
            '16' => '((tgm)/`games`)',                  // TGM
            '17' => '((tga)/`games`)',                  // TGA
            '18' => '(tgm/tga)',                        // TG%
            '19' => '(games)',                          // GAMES
            '20' => '((min)/`games`)',                  // MIN
        ];
        
        // Default to PPG if invalid sort option
        return $sortMap[$sortBy] ?? $sortMap['1'];
    }
}
