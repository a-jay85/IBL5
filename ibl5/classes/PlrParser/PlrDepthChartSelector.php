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
     * A charted team's rows are all written, including traded players, because the DB row holds the
     * new team's chart once that GM submits one.
     *
     * @param array<int, int>|null $sourceTeamByPid pid => teamid in the source .plr. When given, a player
     *        only counts toward charting his team if his source team equals his DB team. A traded
     *        player alone does not mark his new team charted, because until that GM submits a chart
     *        his dc_* values are still his old team's.
     * @return array<int, array{pid: int, teamid: int, dc_pg_depth: int, dc_sg_depth: int, dc_sf_depth: int, dc_pf_depth: int, dc_c_depth: int, dc_can_play_in_game: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}>
     */
    public static function chartedByPid(array $rows, ?array $sourceTeamByPid = null): array
    {
        $chartedTeams = [];
        foreach ($rows as $pid => $row) {
            if ($sourceTeamByPid !== null && ($sourceTeamByPid[$pid] ?? null) !== $row['teamid']) {
                continue;
            }
            if (self::isRealTeam($row['teamid']) && self::hasChartValue($row)) {
                $chartedTeams[$row['teamid']] = $row['teamid'];
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
     * Map pid => teamid from raw .plr lines.
     *
     * @param array<int, string> $lines
     * @return array<int, int>
     */
    public static function sourceTeamByPid(array $lines): array
    {
        $map = [];
        foreach (PlrFileWriter::indexPlayerRecords($lines) as $lineIndex => $pid) {
            $map[$pid] = PlrFileWriter::readField($lines[$lineIndex], 'teamid');
        }

        return $map;
    }

    /**
     * Count the distinct teams present in a chartedByPid() result.
     *
     * @param array<int, array{teamid: int, ...}> $charted
     */
    public static function countTeams(array $charted): int
    {
        return count(array_unique(array_column($charted, 'teamid')));
    }

    private static function isRealTeam(int $teamid): bool
    {
        return $teamid >= 1 && $teamid <= self::MAX_TEAM_ID;
    }

    /**
     * @param array{dc_pg_depth: int, dc_sg_depth: int, dc_sf_depth: int, dc_pf_depth: int, dc_c_depth: int, dc_minutes: int, ...} $row
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
