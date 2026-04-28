<?php

declare(strict_types=1);

namespace SeasonHighs;

use League\LeagueContext;
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
    private string $boxScoresTable;
    private string $boxScoresTeamsTable;
    private string $teamInfoTable;
    private string $scheduleTable;
    private string $playerTable;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->boxScoresTable = $this->resolveTable('ibl_box_scores');
        $this->boxScoresTeamsTable = $this->resolveTable('ibl_box_scores_teams');
        $this->teamInfoTable = $this->resolveTable('ibl_team_info');
        $this->scheduleTable = $this->resolveTable('ibl_schedule');
        $this->playerTable = $this->resolveTable('ibl_plr');
    }

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
        int $limit = 15,
        ?string $locationFilter = null
    ): array {
        // Sanitize the stat name for use as column alias
        $safeStatName = preg_replace('/[^a-zA-Z0-9_]/', '', $statName);
        if ($safeStatName === null) {
            $safeStatName = $statName;
        }

        // Build optional location filter (home/away) — column-to-column comparison, no extra bind params
        $locationCondition = match ($locationFilter) {
            'home' => ' AND bs.teamid = bs.home_teamid',
            'away' => ' AND bs.teamid = bs.visitor_teamid',
            default => '',
        };

        // For player stats (no suffix), JOIN with ibl_plr to get full names
        // The ibl_box_scores.name field truncates longer names (see Boxscore::MAX_PLAYER_NAME_LENGTH)
        // The ibl_plr.name field is varchar(32) which stores full names
        // Also JOIN with ibl_schedule to get box_id for linking to box scores
        // Also JOIN with ibl_team_info to get team colors for styled team cell
        if ($tableSuffix === '') {
            $query = "SELECT p.`pid`, p.`name`, p.`teamid`, t.`team_name` AS `teamname`,
                t.`team_city`, t.`color1`, t.`color2`,
                bs.`game_date` AS `date`, sch.`box_id`,
                COALESCE(bs.`game_of_that_day`, 0) AS game_of_that_day,
                {$statExpression} AS `{$safeStatName}`
                FROM {$this->boxScoresTable} bs
                JOIN {$this->playerTable} p ON bs.pid = p.pid
                LEFT JOIN {$this->teamInfoTable} t ON p.teamid = t.teamid
                LEFT JOIN {$this->scheduleTable} sch ON sch.game_date = bs.game_date AND sch.visitor_teamid = bs.visitor_teamid AND sch.home_teamid = bs.home_teamid
                WHERE bs.`game_date` BETWEEN ? AND ?{$locationCondition}
                ORDER BY `{$safeStatName}` DESC, bs.`game_date` ASC
                LIMIT {$limit}";
        } else {
            // For team stats, JOIN with ibl_team_info to get team ID and colors for linking
            // Also JOIN with ibl_schedule to get box_id for linking to box scores
            // bs IS ibl_box_scores_teams, so game_of_that_day is directly available
            $query = "SELECT t.`teamid`, t.`team_city`, t.`color1`, t.`color2`,
                bs.`name`, bs.`game_date` AS `date`, sch.`box_id`,
                COALESCE(bs.`game_of_that_day`, 0) AS game_of_that_day,
                {$statExpression} AS `{$safeStatName}`
                FROM {$this->boxScoresTeamsTable} bs
                JOIN {$this->teamInfoTable} t ON bs.name = t.team_name
                LEFT JOIN {$this->scheduleTable} sch ON sch.game_date = bs.game_date AND sch.visitor_teamid = bs.visitor_teamid AND sch.home_teamid = bs.home_teamid
                WHERE bs.`game_date` BETWEEN ? AND ?
                ORDER BY `{$safeStatName}` DESC, bs.`game_date` ASC
                LIMIT {$limit}";
        }

        $results = $this->fetchAll($query, "ss", $startDate, $endDate);

        /** @var list<SeasonHighEntry> $normalized */
        $normalized = [];
        foreach ($results as $row) {
            /** @var array<string, int|float|string|null> $row */
            $normalized[] = $this->normalizeRow($row, $safeStatName);
        }

        return $normalized;
    }

    /**
     * @see SeasonHighsRepositoryInterface::getSeasonHighsBatch()
     *
     * @param array<string, string> $stats
     * @return array<string, list<SeasonHighEntry>>
     */
    public function getSeasonHighsBatch(
        array $stats,
        string $tableSuffix,
        string $startDate,
        string $endDate,
        int $limit = 15,
        ?string $locationFilter = null
    ): array {
        if ($stats === []) {
            return [];
        }

        // Initialize result with empty arrays for every requested stat so callers
        // iterating over expected keys never hit an undefined index.
        $byStatName = [];
        foreach ($stats as $statName => $_) {
            $byStatName[$statName] = [];
        }

        $locationCondition = match ($locationFilter) {
            'home' => ' AND bs.teamid = bs.home_teamid',
            'away' => ' AND bs.teamid = bs.visitor_teamid',
            default => '',
        };

        // Each branch contributes top-$limit rows for one stat. Enforce a
        // safe integer for inline LIMIT (no placeholder allowed inside parens).
        $safeLimit = max(1, $limit);

        $branches = [];
        $params = [];
        $types = '';
        foreach ($stats as $statName => $statExpression) {
            if ($tableSuffix === '') {
                $branches[] = "(SELECT ? AS stat_category, p.`pid`, p.`name`, p.`teamid`, t.`team_name` AS `teamname`,
                    t.`team_city`, t.`color1`, t.`color2`,
                    bs.`game_date` AS `date`, sch.`box_id`,
                    COALESCE(bs.`game_of_that_day`, 0) AS game_of_that_day,
                    ({$statExpression}) AS stat_value
                    FROM {$this->boxScoresTable} bs
                    JOIN {$this->playerTable} p ON bs.pid = p.pid
                    LEFT JOIN {$this->teamInfoTable} t ON p.teamid = t.teamid
                    LEFT JOIN {$this->scheduleTable} sch ON sch.game_date = bs.game_date AND sch.visitor_teamid = bs.visitor_teamid AND sch.home_teamid = bs.home_teamid
                    WHERE bs.`game_date` BETWEEN ? AND ?{$locationCondition}
                    ORDER BY stat_value DESC, bs.`game_date` ASC
                    LIMIT {$safeLimit})";
            } else {
                $branches[] = "(SELECT ? AS stat_category, t.`teamid`, t.`team_city`, t.`color1`, t.`color2`,
                    bs.`name`, bs.`game_date` AS `date`, sch.`box_id`,
                    COALESCE(bs.`game_of_that_day`, 0) AS game_of_that_day,
                    ({$statExpression}) AS stat_value
                    FROM {$this->boxScoresTeamsTable} bs
                    JOIN {$this->teamInfoTable} t ON bs.name = t.team_name
                    LEFT JOIN {$this->scheduleTable} sch ON sch.game_date = bs.game_date AND sch.visitor_teamid = bs.visitor_teamid AND sch.home_teamid = bs.home_teamid
                    WHERE bs.`game_date` BETWEEN ? AND ?
                    ORDER BY stat_value DESC, bs.`game_date` ASC
                    LIMIT {$safeLimit})";
            }
            $params[] = $statName;
            $params[] = $startDate;
            $params[] = $endDate;
            $types .= 'sss';
        }

        $query = implode("\nUNION ALL\n", $branches);
        $results = $this->fetchAll($query, $types, ...$params);

        foreach ($results as $row) {
            /** @var array<string, int|float|string|null> $row */
            $statCategory = (string) $row['stat_category'];
            if (!array_key_exists($statCategory, $byStatName)) {
                continue;
            }
            $byStatName[$statCategory][] = $this->normalizeRow($row, 'stat_value');
        }

        foreach ($byStatName as &$entries) {
            usort($entries, static function (array $a, array $b): int {
                if ($a['value'] !== $b['value']) {
                    return $b['value'] <=> $a['value'];
                }
                return strcmp($a['date'], $b['date']);
            });
        }
        unset($entries);

        return $byStatName;
    }

    /**
     * Normalize a raw result row into a SeasonHighEntry.
     *
     * @param array<string, int|float|string|null> $row
     * @return SeasonHighEntry
     */
    private function normalizeRow(array $row, string $valueKey): array
    {
        $entry = [
            'name' => (string) ($row['name'] ?? ''),
            'date' => (string) ($row['date'] ?? ''),
            'value' => (int) ($row[$valueKey] ?? 0),
        ];
        if (isset($row['pid'])) {
            $entry['pid'] = (int) $row['pid'];
        }
        if (isset($row['teamid'])) {
            $entry['teamid'] = (int) $row['teamid'];
            $entry['team_city'] = (string) ($row['team_city'] ?? '');
            $entry['color1'] = (string) ($row['color1'] ?? 'FFFFFF');
            $entry['color2'] = (string) ($row['color2'] ?? '000000');
            if (isset($row['teamname'])) {
                $entry['teamname'] = (string) $row['teamname'];
            }
        }
        if (isset($row['box_id'])) {
            $entry['boxId'] = (int) $row['box_id'];
        }
        if (isset($row['game_of_that_day'])) {
            $entry['gameOfThatDay'] = (int) $row['game_of_that_day'];
        }
        return $entry;
    }

    /**
     * @see SeasonHighsRepositoryInterface::getRcbSeasonHighs()
     *
     * @return list<array{stat_category: string, ranking: int, player_name: string, player_position: string|null, stat_value: int, record_season_year: int}>
     */
    public function getRcbSeasonHighs(int $seasonYear, string $context): array
    {
        /** @var list<array{stat_category: string, ranking: int, player_name: string, player_position: string|null, stat_value: int, record_season_year: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT stat_category, ranking, player_name, player_position, stat_value, record_season_year
             FROM ibl_rcb_season_records
             WHERE season_year = ? AND scope = 'league' AND context = ?
             ORDER BY stat_category, ranking",
            'is',
            $seasonYear,
            $context
        );

        return $rows;
    }
}
