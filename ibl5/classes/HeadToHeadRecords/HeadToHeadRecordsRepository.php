<?php

declare(strict_types=1);

namespace HeadToHeadRecords;

use HeadToHeadRecords\Contracts\HeadToHeadRecordsRepositoryInterface;
use League\League;

/**
 * @phpstan-import-type Dimension from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type Phase from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type Scope from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type AxisEntry from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type MatchupRecord from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type MatrixPayload from HeadToHeadRecordsRepositoryInterface
 */
class HeadToHeadRecordsRepository extends \BaseMysqliRepository implements HeadToHeadRecordsRepositoryInterface
{
    private LogoResolver $logoResolver;

    public function __construct(\mysqli $db, ?LogoResolver $logoResolver = null)
    {
        parent::__construct($db);
        $this->logoResolver = $logoResolver ?? new LogoResolver();
    }

    /**
     * @see HeadToHeadRecordsRepositoryInterface::getMatrix()
     *
     * @param Scope $scope
     * @param Dimension $dimension
     * @param Phase $phase
     * @return MatrixPayload
     */
    public function getMatrix(string $scope, string $dimension, string $phase, int $currentSeasonYear): array
    {
        return match ($dimension) {
            'active_teams' => $this->buildActiveTeamsMatrix($scope, $phase, $currentSeasonYear),
            'all_time_teams' => $this->buildAllTimeTeamsMatrix($scope, $phase, $currentSeasonYear),
            'gms' => $this->buildGmsMatrix($scope, $phase, $currentSeasonYear),
        };
    }

    /**
     * @param Scope $scope
     * @param Phase $phase
     * @return MatrixPayload
     */
    private function buildActiveTeamsMatrix(string $scope, string $phase, int $currentSeasonYear): array
    {
        $axis = $this->getActiveTeamsAxis();
        $gameTypeFilter = $this->getGameTypeFilter($phase);
        $seasonFiltered = ($scope === 'current');

        $sql = $this->buildActiveTeamsPairsQuery($gameTypeFilter, $seasonFiltered, $currentSeasonYear);
        $params = $seasonFiltered ? ['i', $currentSeasonYear] : [''];

        /** @var list<array{self: int, opponent: int, wins: int, losses: int}> $rows */
        $rows = $this->fetchAll($sql, ...$params);

        $matrix = $this->buildMatrixFromPairs($rows);

        return ['axis' => $axis, 'matrix' => $matrix];
    }

    /**
     * @return string SQL query
     */
    private function buildActiveTeamsPairsQuery(string $gameTypeFilter, bool $seasonFiltered, int $currentSeasonYear): string
    {
        $seasonClause = $seasonFiltered ? 'AND bst.season_year = ?' : '';

        return "SELECT
                r.team_id AS self,
                r.opponent_id AS opponent,
                SUM(r.is_win) AS wins,
                SUM(1 - r.is_win) AS losses
            FROM (
                SELECT
                    bst.visitorTeamID AS team_id,
                    bst.homeTeamID AS opponent_id,
                    CASE WHEN (bst.visitorQ1points + bst.visitorQ2points + bst.visitorQ3points + bst.visitorQ4points + COALESCE(bst.visitorOTpoints, 0))
                              > (bst.homeQ1points + bst.homeQ2points + bst.homeQ3points + bst.homeQ4points + COALESCE(bst.homeOTpoints, 0))
                         THEN 1 ELSE 0 END AS is_win,
                    bst.season_year
                FROM ibl_box_scores_teams bst
                WHERE bst.id IN (
                    SELECT MIN(b2.id) FROM ibl_box_scores_teams b2
                    WHERE b2.game_type {$gameTypeFilter}
                    GROUP BY b2.Date, b2.gameOfThatDay, b2.visitorTeamID, b2.homeTeamID
                )
                AND bst.game_type {$gameTypeFilter}
                {$seasonClause}
                AND bst.visitorTeamID BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                AND bst.homeTeamID BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "

                UNION ALL

                SELECT
                    bst.homeTeamID AS team_id,
                    bst.visitorTeamID AS opponent_id,
                    CASE WHEN (bst.homeQ1points + bst.homeQ2points + bst.homeQ3points + bst.homeQ4points + COALESCE(bst.homeOTpoints, 0))
                              > (bst.visitorQ1points + bst.visitorQ2points + bst.visitorQ3points + bst.visitorQ4points + COALESCE(bst.visitorOTpoints, 0))
                         THEN 1 ELSE 0 END AS is_win,
                    bst.season_year
                FROM ibl_box_scores_teams bst
                WHERE bst.id IN (
                    SELECT MIN(b2.id) FROM ibl_box_scores_teams b2
                    WHERE b2.game_type {$gameTypeFilter}
                    GROUP BY b2.Date, b2.gameOfThatDay, b2.visitorTeamID, b2.homeTeamID
                )
                AND bst.game_type {$gameTypeFilter}
                {$seasonClause}
                AND bst.visitorTeamID BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                AND bst.homeTeamID BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
            ) r
            GROUP BY r.team_id, r.opponent_id
            ORDER BY r.team_id, r.opponent_id";
    }

    /**
     * @param Scope $scope
     * @param Phase $phase
     * @return MatrixPayload
     */
    private function buildAllTimeTeamsMatrix(string $scope, string $phase, int $currentSeasonYear): array
    {
        $gameTypeFilter = $this->getGameTypeFilter($phase);
        $seasonFiltered = ($scope === 'current');

        $seasonClause = $seasonFiltered ? 'AND bst.season_year = ?' : '';
        $axisSeasonClause = $seasonFiltered ? 'WHERE fs.season_ending_year = ?' : '';

        // Build axis: each (franchise_id, team_city, team_name) combo
        $axisSql = "SELECT DISTINCT fs.franchise_id, fs.team_city, fs.team_name
            FROM ibl_franchise_seasons fs
            {$axisSeasonClause}
            ORDER BY fs.team_city, fs.team_name";

        /** @var list<array{franchise_id: int, team_city: string, team_name: string}> $axisRows */
        $axisRows = $seasonFiltered
            ? $this->fetchAll($axisSql, 'i', $currentSeasonYear)
            : $this->fetchAll($axisSql, '');

        /** @var list<AxisEntry> $axis */
        $axis = [];
        foreach ($axisRows as $row) {
            $key = $row['franchise_id'] . ':' . $row['team_city'] . ' ' . $row['team_name'];
            $axis[] = [
                'key' => $key,
                'label' => $row['team_city'] . ' ' . $row['team_name'],
                'logo' => $this->logoResolver->resolve($row['franchise_id'], $row['team_name']),
                'franchise_id' => $row['franchise_id'],
            ];
        }

        // Build game data query joining to franchise_seasons for both teams
        $sql = "SELECT
                CONCAT(fs_team.franchise_id, ':', fs_team.team_city, ' ', fs_team.team_name) AS self_key,
                CONCAT(fs_opp.franchise_id, ':', fs_opp.team_city, ' ', fs_opp.team_name) AS opp_key,
                SUM(r.is_win) AS wins,
                SUM(1 - r.is_win) AS losses
            FROM (
                SELECT
                    bst.visitorTeamID AS team_id,
                    bst.homeTeamID AS opponent_id,
                    bst.season_year,
                    CASE WHEN (bst.visitorQ1points + bst.visitorQ2points + bst.visitorQ3points + bst.visitorQ4points + COALESCE(bst.visitorOTpoints, 0))
                              > (bst.homeQ1points + bst.homeQ2points + bst.homeQ3points + bst.homeQ4points + COALESCE(bst.homeOTpoints, 0))
                         THEN 1 ELSE 0 END AS is_win
                FROM ibl_box_scores_teams bst
                WHERE bst.id IN (
                    SELECT MIN(b2.id) FROM ibl_box_scores_teams b2
                    WHERE b2.game_type {$gameTypeFilter}
                    GROUP BY b2.Date, b2.gameOfThatDay, b2.visitorTeamID, b2.homeTeamID
                )
                AND bst.game_type {$gameTypeFilter}
                {$seasonClause}
                AND bst.visitorTeamID BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                AND bst.homeTeamID BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "

                UNION ALL

                SELECT
                    bst.homeTeamID AS team_id,
                    bst.visitorTeamID AS opponent_id,
                    bst.season_year,
                    CASE WHEN (bst.homeQ1points + bst.homeQ2points + bst.homeQ3points + bst.homeQ4points + COALESCE(bst.homeOTpoints, 0))
                              > (bst.visitorQ1points + bst.visitorQ2points + bst.visitorQ3points + bst.visitorQ4points + COALESCE(bst.visitorOTpoints, 0))
                         THEN 1 ELSE 0 END AS is_win
                FROM ibl_box_scores_teams bst
                WHERE bst.id IN (
                    SELECT MIN(b2.id) FROM ibl_box_scores_teams b2
                    WHERE b2.game_type {$gameTypeFilter}
                    GROUP BY b2.Date, b2.gameOfThatDay, b2.visitorTeamID, b2.homeTeamID
                )
                AND bst.game_type {$gameTypeFilter}
                {$seasonClause}
                AND bst.visitorTeamID BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                AND bst.homeTeamID BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
            ) r
            JOIN ibl_franchise_seasons fs_team
                ON fs_team.franchise_id = r.team_id AND fs_team.season_ending_year = r.season_year
            JOIN ibl_franchise_seasons fs_opp
                ON fs_opp.franchise_id = r.opponent_id AND fs_opp.season_ending_year = r.season_year
            GROUP BY self_key, opp_key
            ORDER BY self_key, opp_key";

        /** @var list<array{self_key: string, opp_key: string, wins: int, losses: int}> $rows */
        $rows = $seasonFiltered
            ? $this->fetchAll($sql, 'ii', $currentSeasonYear, $currentSeasonYear)
            : $this->fetchAll($sql, '');

        /** @var array<string, array<string, MatchupRecord>> $matrix */
        $matrix = [];
        foreach ($rows as $row) {
            $matrix[$row['self_key']][$row['opp_key']] = [
                'wins' => $row['wins'],
                'losses' => $row['losses'],
            ];
        }

        return ['axis' => $axis, 'matrix' => $matrix];
    }

    /**
     * @param Scope $scope
     * @param Phase $phase
     * @return MatrixPayload
     */
    private function buildGmsMatrix(string $scope, string $phase, int $currentSeasonYear): array
    {
        $gameTypeFilter = $this->getGameTypeFilter($phase);
        $seasonFiltered = ($scope === 'current');

        $seasonClause = $seasonFiltered ? 'AND bst.season_year = ?' : '';

        // Build axis from gm_tenures
        $axisSeasonClause = $seasonFiltered
            ? 'WHERE ? BETWEEN (gt.start_season_year + gt.is_mid_season_start)
                       AND COALESCE(gt.end_season_year, 9999)'
            : '';

        $axisSql = "SELECT DISTINCT gt.gm_display_name
            FROM ibl_gm_tenures gt
            {$axisSeasonClause}
            ORDER BY gt.gm_display_name";

        /** @var list<array{gm_display_name: string}> $axisRows */
        $axisRows = $seasonFiltered
            ? $this->fetchAll($axisSql, 'i', $currentSeasonYear)
            : $this->fetchAll($axisSql, '');

        /** @var list<AxisEntry> $axis */
        $axis = [];
        foreach ($axisRows as $row) {
            $axis[] = [
                'key' => $row['gm_display_name'],
                'label' => $row['gm_display_name'],
                'logo' => '',
                'franchise_id' => 0,
            ];
        }

        // Build game data query joining to gm_tenures for both teams
        $sql = "SELECT
                gt_team.gm_display_name AS self_key,
                gt_opp.gm_display_name AS opp_key,
                SUM(r.is_win) AS wins,
                SUM(1 - r.is_win) AS losses
            FROM (
                SELECT
                    bst.visitorTeamID AS team_id,
                    bst.homeTeamID AS opponent_id,
                    bst.season_year,
                    CASE WHEN (bst.visitorQ1points + bst.visitorQ2points + bst.visitorQ3points + bst.visitorQ4points + COALESCE(bst.visitorOTpoints, 0))
                              > (bst.homeQ1points + bst.homeQ2points + bst.homeQ3points + bst.homeQ4points + COALESCE(bst.homeOTpoints, 0))
                         THEN 1 ELSE 0 END AS is_win
                FROM ibl_box_scores_teams bst
                WHERE bst.id IN (
                    SELECT MIN(b2.id) FROM ibl_box_scores_teams b2
                    WHERE b2.game_type {$gameTypeFilter}
                    GROUP BY b2.Date, b2.gameOfThatDay, b2.visitorTeamID, b2.homeTeamID
                )
                AND bst.game_type {$gameTypeFilter}
                {$seasonClause}
                AND bst.visitorTeamID BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                AND bst.homeTeamID BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "

                UNION ALL

                SELECT
                    bst.homeTeamID AS team_id,
                    bst.visitorTeamID AS opponent_id,
                    bst.season_year,
                    CASE WHEN (bst.homeQ1points + bst.homeQ2points + bst.homeQ3points + bst.homeQ4points + COALESCE(bst.homeOTpoints, 0))
                              > (bst.visitorQ1points + bst.visitorQ2points + bst.visitorQ3points + bst.visitorQ4points + COALESCE(bst.visitorOTpoints, 0))
                         THEN 1 ELSE 0 END AS is_win
                FROM ibl_box_scores_teams bst
                WHERE bst.id IN (
                    SELECT MIN(b2.id) FROM ibl_box_scores_teams b2
                    WHERE b2.game_type {$gameTypeFilter}
                    GROUP BY b2.Date, b2.gameOfThatDay, b2.visitorTeamID, b2.homeTeamID
                )
                AND bst.game_type {$gameTypeFilter}
                {$seasonClause}
                AND bst.visitorTeamID BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                AND bst.homeTeamID BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
            ) r
            JOIN ibl_gm_tenures gt_team
                ON gt_team.franchise_id = r.team_id
                AND r.season_year BETWEEN (gt_team.start_season_year + gt_team.is_mid_season_start)
                                      AND COALESCE(gt_team.end_season_year, 9999)
            JOIN ibl_gm_tenures gt_opp
                ON gt_opp.franchise_id = r.opponent_id
                AND r.season_year BETWEEN (gt_opp.start_season_year + gt_opp.is_mid_season_start)
                                      AND COALESCE(gt_opp.end_season_year, 9999)
            WHERE gt_team.gm_display_name != gt_opp.gm_display_name
            GROUP BY self_key, opp_key
            ORDER BY self_key, opp_key";

        /** @var list<array{self_key: string, opp_key: string, wins: int, losses: int}> $rows */
        $rows = $seasonFiltered
            ? $this->fetchAll($sql, 'ii', $currentSeasonYear, $currentSeasonYear)
            : $this->fetchAll($sql, '');

        /** @var array<string, array<string, MatchupRecord>> $matrix */
        $matrix = [];
        foreach ($rows as $row) {
            $matrix[$row['self_key']][$row['opp_key']] = [
                'wins' => $row['wins'],
                'losses' => $row['losses'],
            ];
        }

        return ['axis' => $axis, 'matrix' => $matrix];
    }

    /**
     * @return list<AxisEntry>
     */
    private function getActiveTeamsAxis(): array
    {
        /** @var list<array{teamid: int, team_city: string, team_name: string}> $teams */
        $teams = $this->fetchAll(
            "SELECT teamid, team_city, team_name FROM ibl_team_info
             WHERE teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
             ORDER BY teamid ASC",
            ''
        );

        /** @var list<AxisEntry> $axis */
        $axis = [];
        foreach ($teams as $team) {
            $axis[] = [
                'key' => $team['teamid'],
                'label' => $team['team_name'],
                'logo' => 'images/logo/new' . $team['teamid'] . '.png',
                'franchise_id' => $team['teamid'],
            ];
        }

        return $axis;
    }

    /**
     * @param Phase $phase
     * @return string SQL fragment for game_type filter
     */
    private function getGameTypeFilter(string $phase): string
    {
        return match ($phase) {
            'regular' => '= 1',
            'playoffs' => '= 2',
            'heat' => '= 3',
            'all' => 'IN (1, 2, 3)',
        };
    }

    /**
     * @param list<array{self: int, opponent: int, wins: int, losses: int}> $rows
     * @return array<int, array<int, MatchupRecord>>
     */
    private function buildMatrixFromPairs(array $rows): array
    {
        /** @var array<int, array<int, MatchupRecord>> $matrix */
        $matrix = [];
        foreach ($rows as $row) {
            $matrix[$row['self']][$row['opponent']] = [
                'wins' => $row['wins'],
                'losses' => $row['losses'],
            ];
        }

        return $matrix;
    }
}
