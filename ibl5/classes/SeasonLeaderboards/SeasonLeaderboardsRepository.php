<?php

declare(strict_types=1);

namespace SeasonLeaderboards;

use League\League;
use League\LeagueContext;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsRepositoryInterface;

/**
 * @see SeasonLeaderboardsRepositoryInterface
 *
 * @phpstan-import-type LeaderboardFilters from SeasonLeaderboardsRepositoryInterface
 * @phpstan-import-type HistRow from SeasonLeaderboardsRepositoryInterface
 * @phpstan-import-type LeaderboardResult from SeasonLeaderboardsRepositoryInterface
 * @phpstan-import-type TeamRow from SeasonLeaderboardsRepositoryInterface
 */
class SeasonLeaderboardsRepository extends \BaseMysqliRepository implements SeasonLeaderboardsRepositoryInterface
{
    private string $teamInfoTable;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->teamInfoTable = $this->resolveTable('ibl_team_info');
    }

    /**
     * @see SeasonLeaderboardsRepositoryInterface::getSeasonLeaders()
     *
     * SECURITY NOTE: $sortBy is validated and mapped to whitelisted SQL expressions
     * in getSortColumn() method. Dynamic ORDER BY clause is acceptable here because
     * the sort expression is generated from a strict whitelist.
     *
     * @param LeaderboardFilters $filters Filter parameters
     * @return LeaderboardResult Result with rows and count
     */
    public function getSeasonLeaders(array $filters, int $limit = 0): array
    {
        $where = new \Services\QueryConditions(["h.name IS NOT NULL"]);

        $yearFilter = (string) ($filters['year'] ?? '');
        if ($yearFilter !== '') {
            $where->add('h.year = ?', 's', $yearFilter);
        }

        $teamId = (int) ($filters['team'] ?? 0);
        if ($teamId !== 0) {
            $where->add('h.teamid = ?', 'i', $teamId);
        }

        $sortBy = $this->getSortColumn((string) ($filters['sortby'] ?? 'PPG'));

        // NOTE: $sortBy is validated in getSortColumn() against a strict whitelist
        $query = "SELECT h.*, t.team_city, t.color1, t.color2
            FROM ibl_hist h
            LEFT JOIN {$this->teamInfoTable} t ON h.teamid = t.teamid
            WHERE {$where->toWhereClause()} ORDER BY $sortBy DESC"
            . ($limit > 0 ? " LIMIT $limit" : "");

        /** @var list<HistRow> $rows */
        $rows = $this->fetchAll($query, $where->getTypes(), ...$where->getParams());

        return [
            'results' => $rows,
            'count' => count($rows)
        ];
    }

    /**
     * @see SeasonLeaderboardsRepositoryInterface::getTeams()
     *
     * @return list<TeamRow>
     */
    public function getTeams(): array
    {
        /** @var list<TeamRow> $rows */
        $rows = $this->fetchAll(
            "SELECT teamid AS TeamID, team_name AS Team FROM {$this->teamInfoTable} WHERE teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . " ORDER BY teamid ASC"
        );
        return $rows;
    }

    /**
     * @see SeasonLeaderboardsRepositoryInterface::getYears()
     *
     * @return list<int>
     */
    public function getYears(): array
    {
        /** @var list<array{year: int}> $rows */
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
     * All sort options are mapped to pre-defined SQL expressions.
     * String concatenation in ORDER BY clauses is acceptable because values come
     * from this strict whitelist, not user input.
     *
     * @param string $sortBy Sort option identifier (PPG, REB, OREB, DREB, etc.)
     * @return string SQL expression for sorting
     */
    private function getSortColumn(string $sortBy): string
    {
        $sortMap = [
            'PPG' => '((2*`fgm`+`ftm`+`tgm`)/`games`)',
            'REB' => '((`reb`)/`games`)',
            'OREB' => '((`orb`)/`games`)',
            'DREB' => '((`reb`-`orb`)/`games`)',
            'AST' => '((`ast`)/`games`)',
            'STL' => '((`stl`)/`games`)',
            'BLK' => '((`blk`)/`games`)',
            'TO' => '((`tvr`)/`games`)',
            'FOUL' => '((`pf`)/`games`)',
            'QA' => '((((2*fgm+ftm+tgm)+reb+(2*ast)+(2*stl)+(2*blk))-((fga-fgm)+(fta-ftm)+tvr+pf))/games)',
            'FGM' => '((`fgm`)/`games`)',
            'FGA' => '((`fga`)/`games`)',
            'FGP' => '(fgm/fga)',
            'FTM' => '((`ftm`)/`games`)',
            'FTA' => '((`fta`)/`games`)',
            'FTP' => '(ftm/fta)',
            'TGM' => '((`tgm`)/`games`)',
            'TGA' => '((`tga`)/`games`)',
            'TGP' => '(tgm/tga)',
            'GAMES' => '(games)',
            'MIN' => '((`minutes`)/`games`)',
        ];

        return $sortMap[$sortBy] ?? $sortMap['PPG'];
    }
}
