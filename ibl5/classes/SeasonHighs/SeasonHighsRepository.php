<?php

declare(strict_types=1);

namespace SeasonHighs;

use SeasonHighs\Contracts\SeasonHighsRepositoryInterface;
use SeasonHighs\Contracts\SeasonHighsServiceInterface;

/**
 * SeasonHighsRepository - Data access layer for season highs
 *
 * Retrieves season high stats from box score tables.
 *
 * @phpstan-import-type SeasonHighEntry from SeasonHighsServiceInterface
 *
 * @see SeasonHighsRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class SeasonHighsRepository extends \BaseMysqliRepository implements SeasonHighsRepositoryInterface
{
    /**
     * @see SeasonHighsRepositoryInterface::getSeasonHighs()
     *
     * @return list<SeasonHighEntry>
     */
    public function getSeasonHighs(
        string $statExpression,
        string $statName,
        string $tableSuffix,
        string $startDate,
        string $endDate,
        int $limit = 15
    ): array {
        // Sanitize the stat name for use as column alias
        $safeStatName = preg_replace('/[^a-zA-Z0-9_]/', '', $statName);
        if ($safeStatName === null) {
            $safeStatName = $statName;
        }

        // For player stats (no suffix), JOIN with ibl_plr to get full names
        // The ibl_box_scores.name field is varchar(16) which truncates longer names
        // The ibl_plr.name field is varchar(32) which stores full names
        // Also JOIN with ibl_schedule to get BoxID for linking to box scores
        // Also JOIN with ibl_team_info to get team colors for styled team cell
        if ($tableSuffix === '') {
            $query = "SELECT p.`pid`, p.`name`, p.`tid`, p.`teamname`,
                t.`team_city`, t.`color1`, t.`color2`,
                bs.`Date` AS `date`, sch.`BoxID`,
                COALESCE(bst.gameOfThatDay, 0) AS gameOfThatDay,
                {$statExpression} AS `{$safeStatName}`
                FROM ibl_box_scores bs
                JOIN ibl_plr p ON bs.pid = p.pid
                LEFT JOIN ibl_team_info t ON p.tid = t.teamid
                JOIN ibl_schedule sch ON sch.Date = bs.Date AND sch.Visitor = bs.visitorTID AND sch.Home = bs.homeTID
                LEFT JOIN (
                    SELECT Date, visitorTeamID, homeTeamID, MIN(gameOfThatDay) AS gameOfThatDay
                    FROM ibl_box_scores_teams
                    GROUP BY Date, visitorTeamID, homeTeamID
                ) bst ON bst.Date = bs.Date AND bst.visitorTeamID = bs.visitorTID AND bst.homeTeamID = bs.homeTID
                WHERE bs.`Date` BETWEEN ? AND ?
                ORDER BY `{$safeStatName}` DESC, bs.`Date` ASC
                LIMIT {$limit}";
        } else {
            // For team stats, JOIN with ibl_team_info to get team ID and colors for linking
            // Also JOIN with ibl_schedule to get BoxID for linking to box scores
            // bs IS ibl_box_scores_teams, so gameOfThatDay is directly available
            $query = "SELECT t.`teamid`, t.`team_city`, t.`color1`, t.`color2`,
                bs.`name`, bs.`Date` AS `date`, sch.`BoxID`,
                COALESCE(bs.`gameOfThatDay`, 0) AS gameOfThatDay,
                {$statExpression} AS `{$safeStatName}`
                FROM ibl_box_scores{$tableSuffix} bs
                JOIN ibl_team_info t ON bs.name = t.team_name
                JOIN ibl_schedule sch ON sch.Date = bs.Date AND sch.Visitor = bs.visitorTeamID AND sch.Home = bs.homeTeamID
                WHERE bs.`Date` BETWEEN ? AND ?
                ORDER BY `{$safeStatName}` DESC, bs.`Date` ASC
                LIMIT {$limit}";
        }

        $results = $this->fetchAll($query, "ss", $startDate, $endDate);

        // Normalize the results
        /** @var list<SeasonHighEntry> $normalized */
        $normalized = [];
        foreach ($results as $row) {
            /** @var array<string, int|float|string|null> $row */
            $entry = [
                'name' => (string) ($row['name'] ?? ''),
                'date' => (string) ($row['date'] ?? ''),
                'value' => (int) ($row[$safeStatName] ?? 0),
            ];
            // Include pid for player stats (used for profile links)
            if (isset($row['pid'])) {
                $entry['pid'] = (int) $row['pid'];
            }
            // Include team data for player stats (used for styled team cell)
            if (isset($row['tid'])) {
                $entry['tid'] = (int) $row['tid'];
                $entry['teamname'] = (string) ($row['teamname'] ?? '');
                $entry['team_city'] = (string) ($row['team_city'] ?? '');
                $entry['color1'] = (string) ($row['color1'] ?? 'FFFFFF');
                $entry['color2'] = (string) ($row['color2'] ?? '000000');
            }
            // Include teamid and colors for team stats (used for styled team cell)
            if (isset($row['teamid'])) {
                $entry['teamid'] = (int) $row['teamid'];
                $entry['team_city'] = (string) ($row['team_city'] ?? '');
                $entry['color1'] = (string) ($row['color1'] ?? 'FFFFFF');
                $entry['color2'] = (string) ($row['color2'] ?? '000000');
            }
            // Include BoxID and gameOfThatDay for linking dates to box scores
            if (isset($row['BoxID'])) {
                $entry['boxId'] = (int) $row['BoxID'];
            }
            if (isset($row['gameOfThatDay'])) {
                $entry['gameOfThatDay'] = (int) $row['gameOfThatDay'];
            }
            $normalized[] = $entry;
        }

        return $normalized;
    }
}
