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

        $query = "SELECT `name`, `date`, {$statExpression} AS `{$safeStatName}`
            FROM ibl_box_scores{$tableSuffix}
            WHERE date BETWEEN ? AND ?
            ORDER BY `{$safeStatName}` DESC, date ASC
            LIMIT {$limit}";

        $results = $this->fetchAll($query, "ss", $startDate, $endDate);

        // Normalize the results
        $normalized = [];
        foreach ($results as $row) {
            $normalized[] = [
                'name' => $row['name'] ?? '',
                'date' => $row['date'] ?? '',
                'value' => (int) ($row[$safeStatName] ?? 0),
            ];
        }

        return $normalized;
    }
}
