<?php

declare(strict_types=1);

namespace HeadToHeadRecords;

use HeadToHeadRecords\Contracts\HeadToHeadRecordsRepositoryInterface;
use League\League;

/**
 * HeadToHeadRecordsRepository - Builds head-to-head matrix payloads from ibl_box_scores_teams.
 *
 * All SQL uses zero-parameter prepared statements (every variable part is a validated
 * constant from match expressions or typed integers) — mirrors the dbExec idiom in
 * RefreshTeamSeasonRecordsStep.
 *
 * The unique_games CTE de-duplicates duplicate rows for the same game via MIN(id).
 * Score expressions are copied verbatim from RefreshTeamSeasonRecordsStep::INSERT_REGULAR_SEASON_SQL.
 *
 * @phpstan-import-type MatrixPayload from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type AxisEntry from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type MatchupRecord from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type Phase from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type Scope from HeadToHeadRecordsRepositoryInterface
 */
class HeadToHeadRecordsRepository implements HeadToHeadRecordsRepositoryInterface
{
    /** @var \Closure(int, string): string */
    private \Closure $logoResolver;

    public function __construct(
        private readonly \mysqli $db,
        private readonly int $currentSeasonYear,
        ?\Closure $logoResolver = null,
    ) {
        $this->logoResolver = $logoResolver ?? static fn (int $id, string $teamName) => "new{$id}.png";
    }

    /**
     * @return MatrixPayload
     */
    public function buildFranchisesMatrix(string $phase, string $scope): array
    {
        /** @var Phase $phase */
        /** @var Scope $scope */
        $maxTid = League::MAX_REAL_TEAMID;

        $sql = $this->buildBaseSql($phase, $scope) . '
SELECT self_id, opp_id, SUM(won) AS wins, SUM(1 - won) AS losses
FROM perspectives
GROUP BY self_id, opp_id';

        $rows = $this->fetchRows($sql);
        $records = [];
        foreach ($rows as $row) {
            $selfKey = (string)self::intFromMixed($row['self_id']);
            $oppKey  = (string)self::intFromMixed($row['opp_id']);
            $records[$selfKey][$oppKey] = [
                'wins'   => self::intFromMixed($row['wins']),
                'losses' => self::intFromMixed($row['losses']),
            ];
        }

        $axisSQL = sprintf(
            'SELECT teamid, team_city, team_name, color1, color2
             FROM `ibl_team_info`
             WHERE teamid BETWEEN 1 AND %d
             ORDER BY team_name, teamid ASC',
            $maxTid,
        );
        $axisRows = $this->fetchRows($axisSQL);

        $axis = [];
        foreach ($axisRows as $row) {
            $tid  = self::intFromMixed($row['teamid']);
            $city = is_string($row['team_city']) ? $row['team_city'] : '';
            $name = is_string($row['team_name']) ? $row['team_name'] : '';
            $axis[] = [
                'key'               => (string)$tid,
                'franchise_id'      => $tid,
                'label'             => $city . ' ' . $name,
                'sublabel'          => '',
                'color1'            => is_string($row['color1']) ? $row['color1'] : '',
                'color2'            => is_string($row['color2']) ? $row['color2'] : '',
                'logo'              => "new{$tid}.png",
                'link_franchise_id' => $tid,
            ];
        }

        return [
            'dimension' => 'franchises',
            'phase'     => $phase,
            'scope'     => $scope,
            'axis'      => $axis,
            'records'   => $records,
        ];
    }

    /**
     * @return MatrixPayload
     */
    public function buildTeamsMatrix(string $phase, string $scope): array
    {
        /** @var Phase $phase */
        /** @var Scope $scope */
        $maxTid = League::MAX_REAL_TEAMID;

        $sql = $this->buildBaseSql($phase, $scope) . '
SELECT
    s.franchise_id AS self_franchise_id, s.team_city AS self_city, s.team_name AS self_name,
    o.franchise_id AS opp_franchise_id,  o.team_city AS opp_city,  o.team_name AS opp_name,
    SUM(p.won) AS wins, SUM(1 - p.won) AS losses
FROM perspectives p
JOIN `ibl_franchise_seasons` s ON s.franchise_id = p.self_id AND s.season_ending_year = p.season_year
JOIN `ibl_franchise_seasons` o ON o.franchise_id = p.opp_id  AND o.season_ending_year = p.season_year
GROUP BY s.franchise_id, s.team_city, s.team_name, o.franchise_id, o.team_city, o.team_name';

        $rows = $this->fetchRows($sql);
        $records = [];
        foreach ($rows as $row) {
            $selfFid  = self::intFromMixed($row['self_franchise_id']);
            $selfCity = is_string($row['self_city']) ? $row['self_city'] : '';
            $selfName = is_string($row['self_name']) ? $row['self_name'] : '';
            $oppFid   = self::intFromMixed($row['opp_franchise_id']);
            $oppCity  = is_string($row['opp_city']) ? $row['opp_city'] : '';
            $oppName  = is_string($row['opp_name']) ? $row['opp_name'] : '';
            $selfKey  = "{$selfFid}|{$selfCity}|{$selfName}";
            $oppKey   = "{$oppFid}|{$oppCity}|{$oppName}";
            $records[$selfKey][$oppKey] = [
                'wins'   => self::intFromMixed($row['wins']),
                'losses' => self::intFromMixed($row['losses']),
            ];
        }

        $scopeAxisFilter = '';
        if ($scope === 'current') {
            $scopeAxisFilter = ' AND fs.season_ending_year = ' . $this->currentSeasonYear;
        }

        $axisSQL = sprintf(
            'SELECT DISTINCT fs.franchise_id, fs.team_city, fs.team_name,
                    MIN(fs.season_ending_year) AS first_season,
                    CASE WHEN ti.team_city = fs.team_city AND ti.team_name = fs.team_name
                         THEN ti.color1 ELSE COALESCE(eb.color1, \'\') END AS color1,
                    CASE WHEN ti.team_city = fs.team_city AND ti.team_name = fs.team_name
                         THEN ti.color2 ELSE COALESCE(eb.color2, \'\') END AS color2
             FROM `ibl_franchise_seasons` fs
             JOIN `ibl_team_info` ti ON ti.teamid = fs.franchise_id
             LEFT JOIN `ibl_franchise_era_branding` eb
                    ON eb.franchise_id = fs.franchise_id
                   AND eb.team_city COLLATE utf8mb4_unicode_ci = fs.team_city
                   AND eb.team_name COLLATE utf8mb4_unicode_ci = fs.team_name
             WHERE fs.franchise_id BETWEEN 1 AND %d%s
             GROUP BY fs.franchise_id, fs.team_city, fs.team_name,
                      ti.team_city, ti.team_name, ti.color1, ti.color2,
                      eb.color1, eb.color2
             ORDER BY fs.franchise_id, MIN(fs.season_ending_year)',
            $maxTid,
            $scopeAxisFilter,
        );

        $axisRows = $this->fetchRows($axisSQL);
        $axis = [];
        foreach ($axisRows as $row) {
            $fid  = self::intFromMixed($row['franchise_id']);
            $city = is_string($row['team_city']) ? $row['team_city'] : '';
            $name = is_string($row['team_name']) ? $row['team_name'] : '';
            $key  = "{$fid}|{$city}|{$name}";
            $axis[] = [
                'key'               => $key,
                'franchise_id'      => $fid,
                'label'             => $city . ' ' . $name,
                'sublabel'          => '',
                'color1'            => is_string($row['color1']) ? $row['color1'] : '',
                'color2'            => is_string($row['color2']) ? $row['color2'] : '',
                'logo'              => ($this->logoResolver)($fid, $name),
                'link_franchise_id' => $fid,
            ];
        }

        return [
            'dimension' => 'teams',
            'phase'     => $phase,
            'scope'     => $scope,
            'axis'      => $axis,
            'records'   => $records,
        ];
    }

    /**
     * @return MatrixPayload
     */
    public function buildGmsMatrix(string $phase, string $scope): array
    {
        /** @var Phase $phase */
        /** @var Scope $scope */

        $sql = $this->buildBaseSql($phase, $scope) . ',
gm_of AS (
    SELECT p.season_year, p.self_id, p.opp_id, p.won,
        (SELECT t.gm_display_name FROM `ibl_gm_tenures` t
          WHERE t.franchise_id = p.self_id
            AND p.season_year BETWEEN (t.start_season_year + t.is_mid_season_start)
                                  AND COALESCE(t.end_season_year, 9999)
          ORDER BY t.start_season_year ASC, t.id ASC LIMIT 1) AS self_gm,
        (SELECT t.gm_display_name FROM `ibl_gm_tenures` t
          WHERE t.franchise_id = p.opp_id
            AND p.season_year BETWEEN (t.start_season_year + t.is_mid_season_start)
                                  AND COALESCE(t.end_season_year, 9999)
          ORDER BY t.start_season_year ASC, t.id ASC LIMIT 1) AS opp_gm
    FROM perspectives p
)
SELECT self_gm, opp_gm, SUM(won) AS wins, SUM(1 - won) AS losses
FROM gm_of
WHERE self_gm IS NOT NULL AND opp_gm IS NOT NULL AND self_gm <> opp_gm
GROUP BY self_gm, opp_gm';

        $rows = $this->fetchRows($sql);
        $records = [];
        foreach ($rows as $row) {
            $selfGm = is_string($row['self_gm']) ? $row['self_gm'] : '';
            $oppGm  = is_string($row['opp_gm'])  ? $row['opp_gm']  : '';
            if ($selfGm === '' || $oppGm === '') {
                continue;
            }
            $records[$selfGm][$oppGm] = [
                'wins'   => self::intFromMixed($row['wins']),
                'losses' => self::intFromMixed($row['losses']),
            ];
        }

        $axis = $this->buildGmAxis($scope, $records);

        return [
            'dimension' => 'gms',
            'phase'     => $phase,
            'scope'     => $scope,
            'axis'      => $axis,
            'records'   => $records,
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @param Phase $phase
     * @param Scope $scope
     */
    private function buildBaseSql(string $phase, string $scope): string
    {
        $maxTid = League::MAX_REAL_TEAMID;

        return sprintf(
            'WITH unique_games AS (
    SELECT MIN(id) AS id
    FROM `ibl_box_scores_teams` b
    WHERE b.game_type %s
      AND b.visitor_teamid BETWEEN 1 AND %d
      AND b.home_teamid   BETWEEN 1 AND %d
      %s
    GROUP BY b.game_date, b.visitor_teamid, b.home_teamid, b.game_of_that_day
),
games AS (
    SELECT b.season_year, b.visitor_teamid, b.home_teamid,
           (b.visitor_q1_points + b.visitor_q2_points + b.visitor_q3_points + b.visitor_q4_points
            + COALESCE(b.visitor_ot_points, 0)) AS visitor_pts,
           (b.home_q1_points + b.home_q2_points + b.home_q3_points + b.home_q4_points
            + COALESCE(b.home_ot_points, 0)) AS home_pts
    FROM `ibl_box_scores_teams` b
    JOIN unique_games u ON u.id = b.id
),
perspectives AS (
    SELECT season_year, visitor_teamid AS self_id, home_teamid AS opp_id,
           IF(visitor_pts > home_pts, 1, 0) AS won
    FROM games
    UNION ALL
    SELECT season_year, home_teamid AS self_id, visitor_teamid AS opp_id,
           IF(home_pts > visitor_pts, 1, 0) AS won
    FROM games
)',
            $this->gameTypeFilter($phase),
            $maxTid,
            $maxTid,
            $this->scopeFilter($scope),
        );
    }

    /** @param Phase $phase */
    private function gameTypeFilter(string $phase): string
    {
        return match ($phase) {
            'heat'     => '= 3',
            'regular'  => '= 1',
            'playoffs' => '= 2',
            'all'      => 'IN (1, 2, 3)',
        };
    }

    /** @param Scope $scope */
    private function scopeFilter(string $scope): string
    {
        return match ($scope) {
            'all'     => '',
            'current' => 'AND b.season_year = ' . $this->currentSeasonYear,
        };
    }

    /**
     * @param array<string, array<string, MatchupRecord>> $records
     * @return list<AxisEntry>
     */
    private function buildGmAxis(string $scope, array $records): array
    {
        // Determine which GM names to include.
        if ($scope === 'current') {
            $gmSet = [];
            foreach ($records as $selfGm => $oppMap) {
                $gmSet[$selfGm] = true;
                foreach (array_keys($oppMap) as $oppGm) {
                    $gmSet[$oppGm] = true;
                }
            }
            ksort($gmSet);
            $gmNames = array_keys($gmSet);
        } else {
            $tenureRows = $this->fetchRows(
                'SELECT DISTINCT gm_display_name FROM `ibl_gm_tenures` ORDER BY gm_display_name', // @phpstan-ignore-line ibl.orderByMissingTiebreaker
            );
            $gmNames = array_map(
                static fn (array $r): string => is_string($r['gm_display_name']) ? $r['gm_display_name'] : '',
                $tenureRows,
            );
            $gmNames = array_values(array_filter($gmNames, static fn (string $s): bool => $s !== ''));
        }

        if ($gmNames === []) {
            return [];
        }

        // Fetch best tenure per GM: current tenure first (end_season_year IS NULL),
        // then most recent, then lowest id as tiebreaker.
        $allTenures = $this->fetchRows(
            'SELECT gm_display_name, franchise_id, end_season_year, id
             FROM `ibl_gm_tenures`
             ORDER BY gm_display_name,
                      (end_season_year IS NULL) DESC,
                      end_season_year DESC,
                      id ASC',
        );

        /** @var array<string, array<string, mixed>> $bestTenure */
        $bestTenure = [];
        foreach ($allTenures as $row) {
            $gm = is_string($row['gm_display_name']) ? $row['gm_display_name'] : '';
            if ($gm !== '' && !array_key_exists($gm, $bestTenure)) {
                $bestTenure[$gm] = $row;
            }
        }

        // Fetch ibl_team_info for color/identity lookup.
        $tiRows = $this->fetchRows(sprintf(
            'SELECT teamid, team_city, team_name, color1, color2 FROM `ibl_team_info` WHERE teamid BETWEEN 1 AND %d',
            League::MAX_REAL_TEAMID,
        ));
        /** @var array<int, array<string, string>> $teamInfo */
        $teamInfo = [];
        foreach ($tiRows as $row) {
            $tid = self::intFromMixed($row['teamid']);
            $teamInfo[$tid] = [
                'team_city' => is_string($row['team_city']) ? $row['team_city'] : '',
                'team_name' => is_string($row['team_name']) ? $row['team_name'] : '',
                'color1'    => is_string($row['color1']) ? $row['color1'] : '',
                'color2'    => is_string($row['color2']) ? $row['color2'] : '',
            ];
        }

        // Fetch franchise_seasons for retired-era team identity lookup.
        $fsRows = $this->fetchRows(sprintf(
            'SELECT franchise_id, season_ending_year, team_city, team_name FROM `ibl_franchise_seasons` WHERE franchise_id BETWEEN 1 AND %d',
            League::MAX_REAL_TEAMID,
        ));
        /** @var array<string, array<string, string>> $fsBySeason */
        $fsBySeason = [];
        foreach ($fsRows as $row) {
            $fid   = self::intFromMixed($row['franchise_id']);
            $endYr = self::intFromMixed($row['season_ending_year']);
            $key   = $fid . ':' . $endYr;
            $fsBySeason[$key] = [
                'team_city' => is_string($row['team_city']) ? $row['team_city'] : '',
                'team_name' => is_string($row['team_name']) ? $row['team_name'] : '',
            ];
        }

        // Build era branding index keyed by "franchise_id|team_city|team_name".
        $eraRows = $this->fetchRows(
            'SELECT franchise_id, team_city, team_name, color1, color2 FROM `ibl_franchise_era_branding`',
        );
        /** @var array<string, array<string, string>> $eraIndex */
        $eraIndex = [];
        foreach ($eraRows as $row) {
            $fid  = self::intFromMixed($row['franchise_id']);
            $city = is_string($row['team_city']) ? $row['team_city'] : '';
            $name = is_string($row['team_name']) ? $row['team_name'] : '';
            $key  = $fid . '|' . $city . '|' . $name;
            $eraIndex[$key] = [
                'color1' => is_string($row['color1']) ? $row['color1'] : '',
                'color2' => is_string($row['color2']) ? $row['color2'] : '',
            ];
        }

        $axis = [];
        foreach ($gmNames as $gmName) {
            if (!array_key_exists($gmName, $bestTenure)) {
                continue;
            }
            $tenure = $bestTenure[$gmName];
            $fid    = self::intFromMixed($tenure['franchise_id']);

            if ($tenure['end_season_year'] === null) {
                // Current GM: use ibl_team_info colors.
                $color1 = $teamInfo[$fid]['color1'] ?? '';
                $color2 = $teamInfo[$fid]['color2'] ?? '';
                $logo   = "new{$fid}.png";
            } else {
                // Retired GM: find the team identity they managed at end_season_year.
                $endSeason = self::intFromMixed($tenure['end_season_year']);
                $fsKey     = $fid . ':' . $endSeason;
                $fsRow     = $fsBySeason[$fsKey] ?? null;

                if ($fsRow !== null) {
                    $city   = $fsRow['team_city'];
                    $name   = $fsRow['team_name'];
                    $eraKey = $fid . '|' . $city . '|' . $name;
                    $ti     = $teamInfo[$fid] ?? null;

                    if ($ti !== null && $ti['team_city'] === $city && $ti['team_name'] === $name) {
                        // Still the current identity.
                        $color1 = $teamInfo[$fid]['color1'] ?? '';
                        $color2 = $teamInfo[$fid]['color2'] ?? '';
                    } else {
                        $color1 = $eraIndex[$eraKey]['color1'] ?? '';
                        $color2 = $eraIndex[$eraKey]['color2'] ?? '';
                    }
                    $logo = ($this->logoResolver)($fid, $name);
                } else {
                    $color1 = '';
                    $color2 = '';
                    $logo   = "new{$fid}.png";
                }
            }

            $axis[] = [
                'key'               => $gmName,
                'franchise_id'      => $fid,
                'label'             => $gmName,
                'sublabel'          => '',
                'color1'            => $color1,
                'color2'            => $color2,
                'logo'              => $logo,
                'link_franchise_id' => $tenure['end_season_year'] === null ? $fid : 0,
            ];
        }

        return $axis;
    }

    /**
     * Return true when the current season already has at least one game in any phase.
     */
    public function currentSeasonHasGames(): bool
    {
        $maxTid = League::MAX_REAL_TEAMID;
        $year   = $this->currentSeasonYear;

        $sql = sprintf(
            'SELECT EXISTS(
                SELECT 1 FROM `ibl_box_scores_teams`
                WHERE game_type IN (1, 2, 3)
                  AND visitor_teamid BETWEEN 1 AND %d
                  AND home_teamid   BETWEEN 1 AND %d
                  AND season_year = %d
            ) AS has_games',
            $maxTid,
            $maxTid,
            $year,
        );

        $rows = $this->fetchRows($sql);
        return self::intFromMixed($rows[0]['has_games'] ?? 0) > 0;
    }

    /**
     * Execute a zero-parameter SQL statement and return all rows as associative arrays.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchRows(string $sql): array
    {
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new \RuntimeException('Prepare failed: ' . $this->db->error);
        }
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new \RuntimeException('Execute failed: ' . $err);
        }
        $result = $stmt->get_result();
        if ($result === false) {
            $stmt->close();
            throw new \RuntimeException('get_result failed: ' . $stmt->error);
        }
        /** @var list<array<string, mixed>> $rows */
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Safely convert a mixed DB row value to int.
     * mysqli returns strings or ints depending on MYSQLI_OPT_INT_AND_FLOAT_NATIVE.
     */
    private static function intFromMixed(mixed $v): int
    {
        if (is_int($v)) {
            return $v;
        }
        if (is_string($v)) {
            return (int)$v;
        }
        return 0;
    }
}
