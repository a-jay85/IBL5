<?php

declare(strict_types=1);

namespace Standings;

use League\League;
use League\LeagueContext;
use SeriesRecords\SeriesRecordsRepository;
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
    private readonly PythagoreanCalculator $pythagoreanCalculator;

    private readonly StandingsUpdaterRepository $updaterRepository;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->pythagoreanCalculator = new PythagoreanCalculator();
        $this->updaterRepository = new StandingsUpdaterRepository($db, $leagueContext);
    }

    /**
     * Get grouping column names for a region type
     *
     * @param string $region Region name
     * @return array{grouping: string, gbColumn: string, magicNumberColumn: string}
     */
    private function getGroupingColumns(string $region): array
    {
        // Identifier, validated upstream by this method's region check: the
        // returned column names are fixed, backtick-quoted literals from a closed
        // set, concatenated (never interpolated) into getStandingsByRegion()'s
        // query — an invalid region throws below, so no unvalidated token reaches SQL.
        if (in_array($region, League::CONFERENCE_NAMES, true)) {
            return [
                'grouping' => '`conference`',
                'gbColumn' => '`conf_gb`',
                'magicNumberColumn' => '`conf_magic_number`',
            ];
        }

        if (in_array($region, League::DIVISION_NAMES, true)) {
            return [
                'grouping' => '`division`',
                'gbColumn' => '`div_gb`',
                'magicNumberColumn' => '`div_magic_number`',
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
        // $columns holds backtick-quoted identifier literals from a closed set
        // (validated by getGroupingColumns' region check); concatenate them so the
        // query is no longer an interpolated string. Never bind — these are identifiers.
        $gbColumn = $columns['gbColumn'];
        $magicNumberColumn = $columns['magicNumberColumn'];
        $groupingColumn = $columns['grouping'];

        $query = "SELECT
            s.teamid,
            s.team_name,
            s.league_record,
            s.pct,
            s." . $gbColumn . " AS gamesBack,
            s.conf_record,
            s.div_record,
            s.home_record,
            s.away_record,
            s.games_unplayed,
            s." . $magicNumberColumn . " AS magicNumber,
            s.clinched_conference,
            s.clinched_division,
            s.clinched_playoffs,
            s.clinched_league,
            s.wins,
            (s.home_wins + s.home_losses) AS homeGames,
            (s.away_wins + s.away_losses) AS awayGames,
            t.color1,
            t.color2
            FROM `ibl_standings` s
            JOIN `ibl_team_info` t ON s.teamid = t.teamid
            WHERE s." . $groupingColumn . " = ?
            ORDER BY s." . $gbColumn . " ASC,
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
            FROM `ibl_standings` s
            JOIN `ibl_team_info` t ON s.teamid = t.teamid",
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
            "SELECT last_win, last_loss, streak_type, streak, ranking, sos, remaining_sos, sos_rank, remaining_sos_rank FROM `ibl_power` WHERE teamid = ?",
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

        return $this->pythagoreanCalculator->calculate($stats);
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
            "SELECT teamid, last_win, last_loss, streak_type, streak, ranking, sos, remaining_sos, sos_rank, remaining_sos_rank FROM `ibl_power`",
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
            $result[$row['teamid']] = $this->pythagoreanCalculator->calculate($row);
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
        return (new SeriesRecordsRepository($this->db))->getSeriesRecords();
    }

    /**
     * @see StandingsRepositoryInterface::upsertStandings()
     */
    public function upsertStandings(array $params): void
    {
        $this->updaterRepository->upsertStandings($params);
    }

    /**
     * @see StandingsRepositoryInterface::updateMagicNumber()
     */
    public function updateMagicNumber(int $teamid, int $magicNumber, string $magicNumberColumn): void
    {
        $this->updaterRepository->updateMagicNumber($teamid, $magicNumber, $magicNumberColumn);
    }

    /**
     * @see StandingsRepositoryInterface::updateClinchedFlag()
     */
    public function updateClinchedFlag(string $teamName, string $clinchedColumn): void
    {
        $this->updaterRepository->updateClinchedFlag($teamName, $clinchedColumn);
    }

    /**
     * @see StandingsRepositoryInterface::upsertTeamAward()
     */
    public function upsertTeamAward(int $seasonYear, string $teamName, string $awardName): void
    {
        $this->updaterRepository->upsertTeamAward($seasonYear, $teamName, $awardName);
    }

    /**
     * @see StandingsRepositoryInterface::fetchTeamsByRegion()
     *
     * @return list<array{teamid: int, team_name: string, home_wins: int, home_losses: int, away_wins: int, away_losses: int}>
     */
    public function fetchTeamsByRegion(string $grouping, string $region): array
    {
        return $this->updaterRepository->fetchTeamsByRegion($grouping, $region);
    }

    /**
     * @see StandingsRepositoryInterface::fetchTopTeamsByWins()
     *
     * @return list<array{teamid: int, team_name: string, wins: int}>
     */
    public function fetchTopTeamsByWins(?string $grouping, ?string $region): array
    {
        return $this->updaterRepository->fetchTopTeamsByWins($grouping, $region);
    }

    /**
     * @see StandingsRepositoryInterface::fetchLeastLosingTeam()
     *
     * @return array{losses: int}|null
     */
    public function fetchLeastLosingTeam(string $excludeTeamName, ?string $grouping, ?string $region): ?array
    {
        return $this->updaterRepository->fetchLeastLosingTeam($excludeTeamName, $grouping, $region);
    }

    /**
     * @see StandingsRepositoryInterface::isRegionSeasonOver()
     */
    public function isRegionSeasonOver(?string $grouping, ?string $region): bool
    {
        return $this->updaterRepository->isRegionSeasonOver($grouping, $region);
    }

    /**
     * @see StandingsRepositoryInterface::getHeadToHeadWinner()
     */
    public function getHeadToHeadWinner(int $tid1, int $tid2, string $startDate, string $endDate): int
    {
        return $this->updaterRepository->getHeadToHeadWinner($tid1, $tid2, $startDate, $endDate);
    }

    /**
     * @see StandingsRepositoryInterface::fetchTeamMapForSeason()
     *
     * @return array<int, TeamMapping>
     */
    public function fetchTeamMapForSeason(int $seasonEndingYear): array
    {
        return $this->updaterRepository->fetchTeamMapForSeason($seasonEndingYear);
    }

    /**
     * @see StandingsRepositoryInterface::fetchPlayedGamesForSeason()
     *
     * @return list<array{visitor_teamid: int, visitor_score: int, home_teamid: int, home_score: int}>
     */
    public function fetchPlayedGamesForSeason(string $startDate, string $endDate): array
    {
        return $this->updaterRepository->fetchPlayedGamesForSeason($startDate, $endDate);
    }

    /**
     * @see StandingsRepositoryInterface::fetchWinningestTeams()
     *
     * @return list<array{team_name: string, wins: int}>
     */
    public function fetchWinningestTeams(string $conference): array
    {
        return $this->updaterRepository->fetchWinningestTeams($conference);
    }

    /**
     * @see StandingsRepositoryInterface::fetchMostLosingTeams()
     *
     * @return list<array{losses: int}>
     */
    public function fetchMostLosingTeams(string $conference): array
    {
        return $this->updaterRepository->fetchMostLosingTeams($conference);
    }

    /**
     * @see StandingsRepositoryInterface::fetchScheduledGameCountsPerTeam()
     *
     * @return array<int, int>
     */
    public function fetchScheduledGameCountsPerTeam(string $startDate, string $endDate): array
    {
        return $this->updaterRepository->fetchScheduledGameCountsPerTeam($startDate, $endDate);
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
        WHERE bst.game_type = 1 AND " . $filterClause . "
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
        WHERE my.game_type = 1 AND " . $filterClause . "
        GROUP BY fs.franchise_id, fs.team_name, my.season_year";
    }
}
