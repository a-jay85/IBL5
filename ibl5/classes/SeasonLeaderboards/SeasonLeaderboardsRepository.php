<?php

declare(strict_types=1);

namespace SeasonLeaderboards;

use SeasonLeaderboards\Contracts\SeasonLeaderboardsRepositoryInterface;

/**
 * @see SeasonLeaderboardsRepositoryInterface
 * @extends \BaseMysqliRepository
 */
class SeasonLeaderboardsRepository extends \BaseMysqliRepository implements SeasonLeaderboardsRepositoryInterface
{
    public function __construct(object $db)
    {
        parent::__construct($db);
    }

    /**
     * @see SeasonLeaderboardsRepositoryInterface::getSeasonLeaders()
     *
     * SECURITY NOTE: $sortBy is validated and mapped to whitelisted SQL expressions
     * in getSortColumn() method. Dynamic ORDER BY clause is acceptable here because
     * the sort expression is generated from a strict whitelist.
     */
    public function getSeasonLeaders(array $filters, int $limit = 0): array
    {
        $conditions = ["h.name IS NOT NULL"];
        $params = [];
        $types = "";

        // Add year filter if specified
        if (!empty($filters['year'])) {
            $conditions[] = "h.year = ?";
            $types .= "s";
            $params[] = $filters['year'];
        }

        // Add team filter if specified and not "All"
        $teamId = (int)($filters['team'] ?? 0);
        if (!empty($teamId) && $teamId !== 0) {
            $conditions[] = "h.teamid = ?";
            $types .= "i";
            $params[] = $teamId;
        }

        $whereClause = implode(' AND ', $conditions);
        $sortBy = $this->getSortColumn($filters['sortby'] ?? '1');

        // NOTE: $sortBy is validated in getSortColumn() against a strict whitelist
        $query = "SELECT h.*, t.team_city, t.color1, t.color2
            FROM ibl_hist h
            LEFT JOIN ibl_team_info t ON h.teamid = t.teamid
            WHERE $whereClause ORDER BY $sortBy DESC"
            . ($limit > 0 ? " LIMIT $limit" : "");

        $rows = $this->fetchAll($query, $types, ...$params);

        return [
            'result' => $rows,
            'count' => count($rows)
        ];
    }

    /**
     * @see SeasonLeaderboardsRepositoryInterface::getTeams()
     */
    public function getTeams(): array
    {
        return $this->fetchAll("SELECT * FROM ibl_power WHERE TeamID BETWEEN 1 AND 32 ORDER BY TeamID ASC");
    }

    /**
     * @see SeasonLeaderboardsRepositoryInterface::getYears()
     */
    public function getYears(): array
    {
        $rows = $this->fetchAll("SELECT DISTINCT year FROM ibl_hist ORDER BY year DESC");
        
        $years = [];
        foreach ($rows as $row) {
            $years[] = $row['year'];
        }
        
        return $years;
    }

    /**
     * Map sort option to database column/expression for ORDER BY clause
     * 
     * SECURITY NOTE: This method acts as a whitelist for ORDER BY expressions.
     * All possible sort options (1-20) are mapped to pre-defined SQL expressions.
     * String concatenation in ORDER BY clauses is acceptable because values come
     * from this strict whitelist, not user input.
     * 
     * @param string $sortBy Sort option identifier (1-20)
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
