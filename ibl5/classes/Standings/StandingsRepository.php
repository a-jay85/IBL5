<?php

declare(strict_types=1);

namespace Standings;

use League\League;
use League\LeagueContext;
use Standings\Contracts\StandingsRepositoryInterface;

/**
 * StandingsRepository - Data access layer for team standings
 *
 * Retrieves and updates standings data from `ibl_standings` and related tables.
 * Supports both conference and division groupings.
 *
 * @phpstan-import-type StandingsRow from StandingsRepositoryInterface
 * @phpstan-import-type BulkStandingsRow from StandingsRepositoryInterface
 * @phpstan-import-type StreakRow from StandingsRepositoryInterface
 * @phpstan-import-type PythagoreanStats from StandingsRepositoryInterface
 * @phpstan-import-type SeriesRecordRow from StandingsRepositoryInterface
 * @phpstan-import-type TeamMapping from StandingsRepositoryInterface
 * @phpstan-import-type UpsertStandingsParams from StandingsRepositoryInterface
 *
 * @see StandingsRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class StandingsRepository extends \BaseMysqliRepository implements StandingsRepositoryInterface
{
    private string $standingsTable;
    private string $powerTable;
    private string $teamInfoTable;
    private string $scheduleTable;
    private string $leagueConfigTable;
    private string $teamAwardsTable;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->standingsTable = $this->resolveTable('ibl_standings');
        $this->powerTable = $this->resolveTable('ibl_power');
        $this->teamInfoTable = $this->resolveTable('ibl_team_info');
        $this->scheduleTable = $this->resolveTable('ibl_schedule');
        $this->leagueConfigTable = $this->resolveTable('ibl_league_config');
        $this->teamAwardsTable = 'ibl_team_awards';
    }

    /**
     * Get grouping column names for a region type
     *
     * @param string $region Region name
     * @return array{grouping: string, gbColumn: string, magicNumberColumn: string}
     */
    private function getGroupingColumns(string $region): array
    {
        if (in_array($region, League::CONFERENCE_NAMES, true)) {
            return [
                'grouping' => 'conference',
                'gbColumn' => 'conf_gb',
                'magicNumberColumn' => 'conf_magic_number',
            ];
        }

        if (in_array($region, League::DIVISION_NAMES, true)) {
            return [
                'grouping' => 'division',
                'gbColumn' => 'div_gb',
                'magicNumberColumn' => 'div_magic_number',
            ];
        }

        throw new \InvalidArgumentException("Invalid region: {$region}");
    }

    /**
     * @see StandingsRepositoryInterface::getStandingsByRegion()
     *
     * @return list<StandingsRow>
     */
    public function getStandingsByRegion(string $region): array
    {
        $columns = $this->getGroupingColumns($region);

        $query = "SELECT
            s.teamid,
            s.team_name,
            s.league_record,
            s.pct,
            s.{$columns['gbColumn']} AS gamesBack,
            s.conf_record,
            s.div_record,
            s.home_record,
            s.away_record,
            s.games_unplayed,
            s.{$columns['magicNumberColumn']} AS magicNumber,
            s.clinched_conference,
            s.clinched_division,
            s.clinched_playoffs,
            s.clinched_league,
            s.wins,
            (s.home_wins + s.home_losses) AS homeGames,
            (s.away_wins + s.away_losses) AS awayGames,
            t.color1,
            t.color2
            FROM {$this->standingsTable} s
            JOIN {$this->teamInfoTable} t ON s.teamid = t.teamid
            WHERE s.{$columns['grouping']} = ?
            ORDER BY s.{$columns['gbColumn']} ASC,
                (COALESCE(s.clinched_league, 0) * 4
                 + COALESCE(s.clinched_conference, 0) * 3
                 + COALESCE(s.clinched_division, 0) * 2
                 + COALESCE(s.clinched_playoffs, 0)) DESC,
                s.wins DESC";

        /** @var list<StandingsRow> */
        return $this->fetchAll($query, "s", $region);
    }

    /**
     * @see StandingsRepositoryInterface::getAllStandings()
     *
     * @return list<BulkStandingsRow>
     */
    public function getAllStandings(): array
    {
        /** @var list<BulkStandingsRow> */
        return $this->fetchAll(
            "SELECT
                s.teamid, s.team_name, s.league_record, s.pct,
                s.conf_gb, s.div_gb,
                s.conf_record, s.div_record, s.home_record, s.away_record,
                s.games_unplayed,
                s.conf_magic_number, s.div_magic_number,
                s.clinched_conference, s.clinched_division, s.clinched_playoffs, s.clinched_league,
                s.wins,
                (s.home_wins + s.home_losses) AS homeGames,
                (s.away_wins + s.away_losses) AS awayGames,
                s.conference, s.division,
                t.color1, t.color2
            FROM {$this->standingsTable} s
            JOIN {$this->teamInfoTable} t ON s.teamid = t.teamid",
            ""
        );
    }

    /**
    * @see StandingsRepositoryInterface::getTeamStreakData()
     *
     * @return StreakRow|null
     */
    public function getTeamStreakData(int $teamId): ?array
    {
        /** @var StreakRow|null */
        return $this->fetchOne(
            "SELECT last_win, last_loss, streak_type, streak, ranking, sos, remaining_sos, sos_rank, remaining_sos_rank FROM {$this->powerTable} WHERE teamid = ?",
            "i",
            $teamId
        );
    }

    /**
     * @see StandingsRepositoryInterface::getTeamPythagoreanStats()
     *
     * @return PythagoreanStats|null
     */
    public function getTeamPythagoreanStats(int $teamId, int $seasonYear): ?array
    {
        /** @var array{off_fgm: int, off_ftm: int, off_tgm: int, def_fgm: int, def_ftm: int, def_tgm: int}|null $stats */
        $stats = $this->fetchOne(
            "SELECT
                tos.fgm AS off_fgm, tos.ftm AS off_ftm, tos.tgm AS off_tgm,
                tds.fgm AS def_fgm, tds.ftm AS def_ftm, tds.tgm AS def_tgm
            FROM (" . self::buildOffenseSubquery('bst.season_year = ? AND fs.franchise_id = ?') . ") tos
            JOIN (" . self::buildDefenseSubquery('my.season_year = ? AND fs.franchise_id = ?') . ") tds
                ON tos.teamid = tds.teamid AND tos.season_year = tds.season_year",
            "iiii",
            $seasonYear,
            $teamId,
            $seasonYear,
            $teamId
        );

        if ($stats === null) {
            return null;
        }

        return $this->calculatePythagoreanStats($stats);
    }

    /**
     * @see StandingsRepositoryInterface::getAllStreakData()
     *
     * @return array<int, StreakRow>
     */
    public function getAllStreakData(): array
    {
        /** @var list<array{teamid: int, last_win: int, last_loss: int, streak_type: string, streak: int, ranking: int, sos: float|string, remaining_sos: float|string, sos_rank: int, remaining_sos_rank: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT teamid, last_win, last_loss, streak_type, streak, ranking, sos, remaining_sos, sos_rank, remaining_sos_rank FROM {$this->powerTable}",
            ""
        );

        /** @var array<int, StreakRow> $result */
        $result = [];
        foreach ($rows as $row) {
            $result[$row['teamid']] = [
                'last_win' => $row['last_win'],
                'last_loss' => $row['last_loss'],
                'streak_type' => $row['streak_type'],
                'streak' => $row['streak'],
                'ranking' => $row['ranking'],
                'sos' => $row['sos'],
                'remaining_sos' => $row['remaining_sos'],
                'sos_rank' => $row['sos_rank'],
                'remaining_sos_rank' => $row['remaining_sos_rank'],
            ];
        }

        return $result;
    }

    /**
     * @see StandingsRepositoryInterface::getAllPythagoreanStats()
     *
     * @return array<int, PythagoreanStats>
     */
    public function getAllPythagoreanStats(int $seasonYear): array
    {
        /** @var list<array{teamid: int, off_fgm: int, off_ftm: int, off_tgm: int, def_fgm: int, def_ftm: int, def_tgm: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT
                tos.teamid,
                tos.fgm AS off_fgm, tos.ftm AS off_ftm, tos.tgm AS off_tgm,
                tds.fgm AS def_fgm, tds.ftm AS def_ftm, tds.tgm AS def_tgm
            FROM (" . self::buildOffenseSubquery('bst.season_year = ?') . ") tos
            JOIN (" . self::buildDefenseSubquery('my.season_year = ?') . ") tds
                ON tos.teamid = tds.teamid AND tos.season_year = tds.season_year",
            "ii",
            $seasonYear,
            $seasonYear
        );

        /** @var array<int, PythagoreanStats> $result */
        $result = [];
        foreach ($rows as $row) {
            $result[$row['teamid']] = $this->calculatePythagoreanStats($row);
        }

        return $result;
    }

    /**
     * @see StandingsRepositoryInterface::getSeriesRecords()
     *
     * @return list<SeriesRecordRow>
     */
    public function getSeriesRecords(): array
    {
        /** @var list<SeriesRecordRow> */
        return $this->fetchAll(
            "SELECT self, opponent, wins, losses FROM vw_series_records ORDER BY self, opponent",
            ""
        );
    }

    /**
     * @see StandingsRepositoryInterface::upsertStandings()
     */
    public function upsertStandings(array $params): void
    {
        $this->execute(
            "INSERT INTO {$this->standingsTable} (
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
        $this->execute(
            "UPDATE {$this->standingsTable} SET {$magicNumberColumn} = ? WHERE teamid = ?",
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
        $this->execute(
            "UPDATE {$this->standingsTable} SET {$clinchedColumn} = 1 WHERE team_name = ?",
            "s",
            $teamName
        );
    }

    /**
     * @see StandingsRepositoryInterface::upsertTeamAward()
     */
    public function upsertTeamAward(int $seasonYear, string $teamName, string $awardName): void
    {
        $this->execute(
            "INSERT INTO `{$this->teamAwardsTable}` (year, name, award)
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
        /** @var list<array{teamid: int, team_name: string, home_wins: int, home_losses: int, away_wins: int, away_losses: int}> */
        return $this->fetchAll(
            "SELECT teamid, team_name, home_wins, home_losses, away_wins, away_losses
            FROM {$this->standingsTable}
            WHERE {$grouping} = ?
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
            /** @var list<array{teamid: int, team_name: string, wins: int}> */
            return $this->fetchAll(
                "SELECT teamid, team_name, home_wins + away_wins AS wins
                FROM {$this->standingsTable}
                WHERE {$grouping} = ?
                ORDER BY wins DESC
                LIMIT 2",
                "s",
                $region
            );
        }

        /** @var list<array{teamid: int, team_name: string, wins: int}> */
        return $this->fetchAll(
            "SELECT teamid, team_name, home_wins + away_wins AS wins
            FROM {$this->standingsTable}
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
            /** @var array{losses: int}|null */
            return $this->fetchOne(
                "SELECT home_losses + away_losses AS losses
                FROM {$this->standingsTable}
                WHERE {$grouping} = ?
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
            FROM {$this->standingsTable}
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
            $result = $this->fetchOne(
                "SELECT MAX(games_unplayed) AS maxLeft FROM {$this->standingsTable} WHERE {$grouping} = ?",
                "s",
                $region
            );
        } else {
            $result = $this->fetchOne(
                "SELECT MAX(games_unplayed) AS maxLeft FROM {$this->standingsTable}",
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
            FROM {$this->scheduleTable}
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
            FROM {$this->leagueConfigTable}
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
            FROM {$this->scheduleTable}
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
            FROM {$this->standingsTable}
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
            FROM {$this->standingsTable}
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
                SELECT visitor_teamid AS teamid FROM {$this->scheduleTable}
                WHERE game_date BETWEEN ? AND ?
                UNION ALL
                SELECT home_teamid AS teamid FROM {$this->scheduleTable}
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

    /**
     * Calculate Pythagorean stats from raw shooting data
     *
     * @param array{off_fgm: int, off_ftm: int, off_tgm: int, def_fgm: int, def_ftm: int, def_tgm: int} $stats
     * @return PythagoreanStats
     */
    private function calculatePythagoreanStats(array $stats): array
    {
        $pointsScored = \BasketballStats\StatsFormatter::calculatePoints(
            $stats['off_fgm'],
            $stats['off_ftm'],
            $stats['off_tgm']
        );

        $pointsAllowed = \BasketballStats\StatsFormatter::calculatePoints(
            $stats['def_fgm'],
            $stats['def_ftm'],
            $stats['def_tgm']
        );

        return [
            'pointsScored' => $pointsScored,
            'pointsAllowed' => $pointsAllowed,
        ];
    }

    /**
     * Build inlined offense stats subquery with filter pushed before GROUP BY.
     */
    private static function buildOffenseSubquery(string $filterClause): string
    {
        return "SELECT fs.franchise_id AS teamid, fs.team_name AS name, bst.season_year,
            CAST(SUM(bst.game_2gm + bst.game_3gm) AS SIGNED) AS fgm,
            CAST(SUM(bst.game_ftm) AS SIGNED) AS ftm,
            CAST(SUM(bst.game_3gm) AS SIGNED) AS tgm
        FROM `ibl_box_scores_teams` bst
        JOIN `ibl_franchise_seasons` fs
            ON fs.team_name = bst.name AND fs.season_ending_year = bst.season_year
        WHERE bst.game_type = 1 AND {$filterClause}
        GROUP BY fs.franchise_id, fs.team_name, bst.season_year";
    }

    /**
     * Build inlined defense stats subquery with filter pushed before GROUP BY.
     */
    private static function buildDefenseSubquery(string $filterClause): string
    {
        return "SELECT fs.franchise_id AS teamid, fs.team_name AS name, my.season_year,
            CAST(SUM(opp.game_2gm + opp.game_3gm) AS SIGNED) AS fgm,
            CAST(SUM(opp.game_ftm) AS SIGNED) AS ftm,
            CAST(SUM(opp.game_3gm) AS SIGNED) AS tgm
        FROM `ibl_box_scores_teams` my
        JOIN `ibl_box_scores_teams` opp
            ON my.game_date = opp.game_date
            AND my.visitor_teamid = opp.visitor_teamid
            AND my.home_teamid = opp.home_teamid
            AND my.game_of_that_day = opp.game_of_that_day
            AND my.name <> opp.name
        JOIN `ibl_franchise_seasons` fs
            ON fs.team_name = my.name AND fs.season_ending_year = my.season_year
        WHERE my.game_type = 1 AND {$filterClause}
        GROUP BY fs.franchise_id, fs.team_name, my.season_year";
    }
}
