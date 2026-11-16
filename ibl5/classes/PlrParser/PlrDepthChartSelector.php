<?php

declare(strict_types=1);

namespace PlrParser;

/**
 * Picks the depth-chart rows the JSB export may write.
 *
 * A team counts as charted when any of its players has dc_minutes > 0 or any
 * dc_*_depth > 0. Post-sim parsing never writes those columns, so an all-zero
 * team never submitted a chart and its JSB bytes must pass through unchanged.
 */
final class PlrDepthChartSelector
{
    private const MAX_TEAM_ID = 32;

    private const DEPTH_COLUMNS = ['dc_pg_depth', 'dc_sg_depth', 'dc_sf_depth', 'dc_pf_depth', 'dc_c_depth'];

    /**
     * Return the pid-keyed rows of charted teams only.
     *
     * @param array<int, array{pid: int, teamid: int, dc_pg_depth: int, dc_sg_depth: int, dc_sf_depth: int, dc_pf_depth: int, dc_c_depth: int, dc_can_play_in_game: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}> $rows
     * @return array<int, array{pid: int, teamid: int, dc_pg_depth: int, dc_sg_depth: int, dc_sf_depth: int, dc_pf_depth: int, dc_c_depth: int, dc_can_play_in_game: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}>
     */
    public static function chartedByPid(array $rows): array
    {
        $chartedTeams = [];
        foreach ($rows as $row) {
            if (self::isRealTeam($row['teamid']) && self::hasChartValue($row)) {
                $chartedTeams[$row['teamid']] = true;
            }
        }

        $result = [];
        foreach ($rows as $pid => $row) {
            if (isset($chartedTeams[$row['teamid']])) {
                $result[$pid] = $row;
            }
        }

        return $result;
    }

    /**
     * Count the distinct teams present in a chartedByPid() result.
     *
     * @param array<int, array{teamid: int}> $charted
     */
    public static function countTeams(array $charted): int
    {
        $teams = [];
        foreach ($charted as $row) {
            $teams[$row['teamid']] = true;
        }

        return count($teams);
    }

    private static function isRealTeam(int $teamid): bool
    {
        return $teamid >= 1 && $teamid <= self::MAX_TEAM_ID;
    }

    /**
     * @param array{dc_pg_depth: int, dc_sg_depth: int, dc_sf_depth: int, dc_pf_depth: int, dc_c_depth: int, dc_minutes: int} $row
     */
    private static function hasChartValue(array $row): bool
    {
        if ($row['dc_minutes'] > 0) {
            return true;
        }

        foreach (self::DEPTH_COLUMNS as $column) {
            if ($row[$column] > 0) {
                return true;
            }
        }

        return false;
    }
}
