<?php

declare(strict_types=1);

namespace Standings;

use League\LeagueContext;
use Standings\Contracts\StandingsRepositoryInterface;

/**
 * StandingsUpdaterRepository - Write-side and computation data access for team standings
 *
 * Handles standing upserts, magic-number/clinch-flag updates, award records,
 * and computation queries used by the standings updater pipeline.
 *
 * @phpstan-import-type TeamMapping from StandingsRepositoryInterface
 * @phpstan-import-type UpsertStandingsParams from StandingsRepositoryInterface
 */
class StandingsUpdaterRepository extends \BaseMysqliRepository
{
    private string $teamAwardsTable;

    /**
     * Closed allowlists for the column identifiers that callers may pass into the
     * setters/filters below. A column name is a SQL **identifier**, never a
     * bindable value, so it cannot be a `?` parameter — it is validated against a
     * fixed set (mirrors {@see \CareerLeaderboards\CareerLeaderboardsRepository}'s
     * VALID_TABLES allowlist) and rejected with InvalidArgumentException, then
     * concatenated backticked into the query. This closes the injection shape
     * BanSqlStringInterpolationRule flags. The accepted values match exactly what
     * {@see \Updater\StandingsUpdater} / {@see \Updater\StandingsGrouper} emit.
     *
     * @var list<string>
     */
    private const VALID_GROUPING_COLUMNS = ['conference', 'division'];

    /** @var list<string> */
    private const VALID_MAGIC_NUMBER_COLUMNS = ['conf_magic_number', 'div_magic_number'];

    /** @var list<string> */
    private const VALID_CLINCHED_COLUMNS = [
        'clinched_conference',
        'clinched_division',
        'clinched_playoffs',
        'clinched_league',
    ];

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->teamAwardsTable = 'ibl_team_awards';
    }

    /**
     * Validate a caller-supplied column identifier against a closed allowlist and
     * return it backtick-quoted for safe concatenation into query text. Throws on
     * any value outside the allowlist — identifiers are never bound, so this is the
     * only safe way to splice them.
     *
     * @param list<string> $allowed
     */
    private static function backtickAllowedColumn(string $column, array $allowed, string $kind): string
    {
        if (!in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid {$kind} column: {$column}");
        }

        return '`' . $column . '`';
    }

    /**
     * @see StandingsRepositoryInterface::upsertStandings()
     */
    public function upsertStandings(array $params): void
    {
        $this->execute(
            "INSERT INTO `ibl_standings` (
                teamid, team_name, league_record, wins, losses, pct, games_unplayed,
                conference, conf_gb, conf_record,
                division, div_gb, div_record,
                home_record, away_record,
                conf_wins, conf_losses, div_wins, div_losses,
                home_wins, home_losses, away_wins, away_losses
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                team_name = VALUES(team_name),
                league_record = VALUES(league_record),
                wins = VALUES(wins),
                losses = VALUES(losses),
                pct = VALUES(pct),
                games_unplayed = VALUES(games_unplayed),
                conference = VALUES(conference),
                conf_gb = VALUES(conf_gb),
                conf_record = VALUES(conf_record),
                division = VALUES(division),
                div_gb = VALUES(div_gb),
                div_record = VALUES(div_record),
                home_record = VALUES(home_record),
                away_record = VALUES(away_record),
                conf_wins = VALUES(conf_wins),
                conf_losses = VALUES(conf_losses),
                div_wins = VALUES(div_wins),
                div_losses = VALUES(div_losses),
                home_wins = VALUES(home_wins),
                home_losses = VALUES(home_losses),
                away_wins = VALUES(away_wins),
                away_losses = VALUES(away_losses),
                conf_magic_number = NULL,
                div_magic_number = NULL,
                clinched_conference = NULL,
                clinched_division = NULL,
                clinched_playoffs = NULL,
                clinched_league = NULL",
            "issiidisdssdsssiiiiiiii",
            $params['teamid'],
            $params['teamName'],
            $params['leagueRecord'],
            $params['wins'],
            $params['losses'],
            $params['pct'],
            $params['gamesUnplayed'],
            $params['conference'],
            $params['confGb'],
            $params['confRecord'],
            $params['division'],
            $params['divGb'],
            $params['divRecord'],
            $params['homeRecord'],
            $params['awayRecord'],
            $params['confWins'],
            $params['confLosses'],
            $params['divWins'],
            $params['divLosses'],
            $params['homeWins'],
            $params['homeLosses'],
            $params['awayWins'],
            $params['awayLosses']
        );
    }

    /**
     * @see StandingsRepositoryInterface::updateMagicNumber()
     */
    public function updateMagicNumber(int $teamid, int $magicNumber, string $magicNumberColumn): void
    {
        $column = self::backtickAllowedColumn(
            $magicNumberColumn,
            self::VALID_MAGIC_NUMBER_COLUMNS,
            'magic number'
        );
        $this->execute(
            "UPDATE `ibl_standings` SET " . $column . " = ? WHERE teamid = ?",
            "ii",
            $magicNumber,
            $teamid
        );
    }

    /**
     * @see StandingsRepositoryInterface::updateClinchedFlag()
     */
    public function updateClinchedFlag(string $teamName, string $clinchedColumn): void
    {
        $column = self::backtickAllowedColumn(
            $clinchedColumn,
            self::VALID_CLINCHED_COLUMNS,
            'clinched'
        );
        $this->execute(
            "UPDATE `ibl_standings` SET " . $column . " = 1 WHERE team_name = ?",
            "s",
            $teamName
        );
    }

    /**
     * @see StandingsRepositoryInterface::upsertTeamAward()
     */
    public function upsertTeamAward(int $seasonYear, string $teamName, string $awardName): void
    {
        // $teamAwardsTable is the fixed property 'ibl_team_awards' (constructor-set,
        // never user input); concatenate it backticked instead of interpolating.
        $this->execute(
            "INSERT INTO `" . $this->teamAwardsTable . "` (year, name, award)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name)",
            "iss",
            $seasonYear,
            $teamName,
            $awardName
        );
    }

    /**
     * @see StandingsRepositoryInterface::fetchTeamsByRegion()
     *
     * @return list<array{teamid: int, team_name: string, home_wins: int, home_losses: int, away_wins: int, away_losses: int}>
     */
    public function fetchTeamsByRegion(string $grouping, string $region): array
    {
        $groupingColumn = self::backtickAllowedColumn($grouping, self::VALID_GROUPING_COLUMNS, 'grouping');
        /** @var list<array{teamid: int, team_name: string, home_wins: int, home_losses: int, away_wins: int, away_losses: int}> */
        return $this->fetchAll(
            "SELECT teamid, team_name, home_wins, home_losses, away_wins, away_losses
            FROM `ibl_standings`
            WHERE " . $groupingColumn . " = ?
            ORDER BY pct DESC",
            "s",
            $region
        );
    }

    /**
     * @see StandingsRepositoryInterface::fetchTopTeamsByWins()
     *
     * @return list<array{teamid: int, team_name: string, wins: int}>
     */
    public function fetchTopTeamsByWins(?string $grouping, ?string $region): array
    {
        if ($grouping !== null && $region !== null) {
            $groupingColumn = self::backtickAllowedColumn($grouping, self::VALID_GROUPING_COLUMNS, 'grouping');
            /** @var list<array{teamid: int, team_name: string, wins: int}> */
            return $this->fetchAll(
                "SELECT teamid, team_name, home_wins + away_wins AS wins
                FROM `ibl_standings`
                WHERE " . $groupingColumn . " = ?
                ORDER BY wins DESC
                LIMIT 2",
                "s",
                $region
            );
        }

        /** @var list<array{teamid: int, team_name: string, wins: int}> */
        return $this->fetchAll(
            "SELECT teamid, team_name, home_wins + away_wins AS wins
            FROM `ibl_standings`
            ORDER BY wins DESC
            LIMIT 2",
            ""
        );
    }

    /**
     * @see StandingsRepositoryInterface::fetchLeastLosingTeam()
     *
     * @return array{losses: int}|null
     */
    public function fetchLeastLosingTeam(string $excludeTeamName, ?string $grouping, ?string $region): ?array
    {
        if ($grouping !== null && $region !== null) {
            $groupingColumn = self::backtickAllowedColumn($grouping, self::VALID_GROUPING_COLUMNS, 'grouping');
            /** @var array{losses: int}|null */
            return $this->fetchOne(
                "SELECT home_losses + away_losses AS losses
                FROM `ibl_standings`
                WHERE " . $groupingColumn . " = ?
                    AND team_name <> ?
                ORDER BY losses ASC
                LIMIT 1",
                "ss",
                $region,
                $excludeTeamName
            );
        }

        /** @var array{losses: int}|null */
        return $this->fetchOne(
            "SELECT home_losses + away_losses AS losses
            FROM `ibl_standings`
            WHERE team_name <> ?
            ORDER BY losses ASC
            LIMIT 1",
            "s",
            $excludeTeamName
        );
    }

    /**
     * @see StandingsRepositoryInterface::isRegionSeasonOver()
     */
    public function isRegionSeasonOver(?string $grouping, ?string $region): bool
    {
        if ($grouping !== null && $grouping !== '' && $region !== null && $region !== '') {
            $groupingColumn = self::backtickAllowedColumn($grouping, self::VALID_GROUPING_COLUMNS, 'grouping');
            $result = $this->fetchOne(
                "SELECT MAX(games_unplayed) AS maxLeft FROM `ibl_standings` WHERE " . $groupingColumn . " = ?",
                "s",
                $region
            );
        } else {
            $result = $this->fetchOne(
                "SELECT MAX(games_unplayed) AS maxLeft FROM `ibl_standings`",
                ""
            );
        }

        return $result !== null && $result['maxLeft'] === 0;
    }

    /**
     * @see StandingsRepositoryInterface::getHeadToHeadWinner()
     */
    public function getHeadToHeadWinner(int $tid1, int $tid2, string $startDate, string $endDate): int
    {
        $result = $this->fetchOne(
            "SELECT
                COUNT(*) AS total_games,
                SUM(CASE
                    WHEN (visitor_teamid = ? AND visitor_score > home_score) OR (home_teamid = ? AND home_score > visitor_score) THEN 1
                    ELSE 0
                END) AS team1_wins
            FROM `ibl_schedule`
            WHERE visitor_score > 0 AND home_score > 0
            AND game_date BETWEEN ? AND ?
            AND ((visitor_teamid = ? AND home_teamid = ?) OR (visitor_teamid = ? AND home_teamid = ?))",
            "iissiiii",
            $tid1, $tid1,
            $startDate, $endDate,
            $tid1, $tid2, $tid2, $tid1
        );

        if ($result === null) {
            return $tid1;
        }

        /** @var int $totalGames */
        $totalGames = $result['total_games'];
        /** @var int $team1Wins */
        $team1Wins = $result['team1_wins'];
        $team2Wins = $totalGames - $team1Wins;

        return $team1Wins >= $team2Wins ? $tid1 : $tid2;
    }

    /**
     * @see StandingsRepositoryInterface::fetchTeamMapForSeason()
     *
     * @return array<int, TeamMapping>
     */
    public function fetchTeamMapForSeason(int $seasonEndingYear): array
    {
        /** @var list<array{team_slot: int, team_name: string, conference: string, division: string}> $rows */
        $rows = $this->fetchAll(
            "SELECT team_slot, team_name, conference, division
            FROM `ibl_league_config`
            WHERE season_ending_year = ?",
            "i",
            $seasonEndingYear
        );

        /** @var array<int, TeamMapping> $map */
        $map = [];
        foreach ($rows as $row) {
            $map[$row['team_slot']] = [
                'conference' => $row['conference'],
                'division' => $row['division'],
                'teamName' => $row['team_name'],
            ];
        }

        return $map;
    }

    /**
     * @see StandingsRepositoryInterface::fetchPlayedGamesForSeason()
     *
     * @return list<array{visitor_teamid: int, visitor_score: int, home_teamid: int, home_score: int}>
     */
    public function fetchPlayedGamesForSeason(string $startDate, string $endDate): array
    {
        /** @var list<array{visitor_teamid: int, visitor_score: int, home_teamid: int, home_score: int}> */
        return $this->fetchAll(
            "SELECT visitor_teamid, visitor_score, home_teamid, home_score
            FROM `ibl_schedule`
            WHERE visitor_score > 0 AND home_score > 0
            AND game_date BETWEEN ? AND ?
            ORDER BY game_date ASC",
            "ss",
            $startDate,
            $endDate
        );
    }

    /**
     * @see StandingsRepositoryInterface::fetchWinningestTeams()
     *
     * @return list<array{team_name: string, wins: int}>
     */
    public function fetchWinningestTeams(string $conference): array
    {
        /** @var list<array{team_name: string, wins: int}> */
        return $this->fetchAll(
            "SELECT team_name, home_wins + away_wins AS wins
            FROM `ibl_standings`
            WHERE conference = ?
            ORDER BY wins DESC
            LIMIT 8",
            "s",
            $conference
        );
    }

    /**
     * @see StandingsRepositoryInterface::fetchMostLosingTeams()
     *
     * @return list<array{losses: int}>
     */
    public function fetchMostLosingTeams(string $conference): array
    {
        /** @var list<array{losses: int}> */
        return $this->fetchAll(
            "SELECT home_losses + away_losses AS losses
            FROM `ibl_standings`
            WHERE conference = ?
            ORDER BY losses DESC
            LIMIT 6",
            "s",
            $conference
        );
    }

    /**
     * @see StandingsRepositoryInterface::fetchScheduledGameCountsPerTeam()
     *
     * @return array<int, int>
     */
    public function fetchScheduledGameCountsPerTeam(string $startDate, string $endDate): array
    {
        /** @var list<array{teamid: int, game_count: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT teamid, COUNT(*) AS game_count FROM (
                SELECT visitor_teamid AS teamid FROM `ibl_schedule`
                WHERE game_date BETWEEN ? AND ?
                UNION ALL
                SELECT home_teamid AS teamid FROM `ibl_schedule`
                WHERE game_date BETWEEN ? AND ?
            ) AS all_games
            GROUP BY teamid",
            "ssss",
            $startDate, $endDate, $startDate, $endDate
        );

        $result = [];
        foreach ($rows as $row) {
            $result[$row['teamid']] = $row['game_count'];
        }
        return $result;
    }
}
