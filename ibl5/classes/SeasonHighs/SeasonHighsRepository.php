<?php

declare(strict_types=1);

namespace SeasonHighs;

use SeasonHighs\Contracts\SeasonHighsRepositoryInterface;

/**
 * SeasonHighsRepository - Data access layer for season highs
 *
 * Retrieves season high stats from box score tables.
 *
 * @see SeasonHighsRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class SeasonHighsRepository extends \BaseMysqliRepository implements SeasonHighsRepositoryInterface
{
    /**
     * @see SeasonHighsRepositoryInterface::getSeasonHighs()
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

        // For player stats (no suffix), JOIN with ibl_plr to get full names
        // The ibl_box_scores.name field is varchar(16) which truncates longer names
        // The ibl_plr.name field is varchar(32) which stores full names
        if ($tableSuffix === '') {
            $query = "SELECT p.`pid`, p.`name`, bs.`Date` AS `date`, {$statExpression} AS `{$safeStatName}`
                FROM ibl_box_scores bs
                JOIN ibl_plr p ON bs.pid = p.pid
                WHERE bs.`Date` BETWEEN ? AND ?
                ORDER BY `{$safeStatName}` DESC, bs.`Date` ASC
                LIMIT {$limit}";
        } else {
            // For team stats, JOIN with ibl_team_info to get team ID for linking
            $query = "SELECT t.`teamid`, bs.`name`, bs.`Date` AS `date`, {$statExpression} AS `{$safeStatName}`
                FROM ibl_box_scores{$tableSuffix} bs
                JOIN ibl_team_info t ON bs.name = t.team_name
                WHERE bs.`Date` BETWEEN ? AND ?
                ORDER BY `{$safeStatName}` DESC, bs.`Date` ASC
                LIMIT {$limit}";
        }

        $results = $this->fetchAll($query, "ss", $startDate, $endDate);

        // Normalize the results
        $normalized = [];
        foreach ($results as $row) {
            $entry = [
                'name' => $row['name'] ?? '',
                'date' => $row['date'] ?? '',
                'value' => (int) ($row[$safeStatName] ?? 0),
            ];
            // Include pid for player stats (used for profile links)
            if (isset($row['pid'])) {
                $entry['pid'] = (int) $row['pid'];
            }
            // Include teamid for team stats (used for team page links)
            if (isset($row['teamid'])) {
                $entry['teamid'] = (int) $row['teamid'];
            }
            $normalized[] = $entry;
        }

        return $normalized;
    }
}
