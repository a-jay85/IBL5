<?php

declare(strict_types=1);

namespace Standings;

use Standings\Contracts\StandingsRepositoryInterface;

/**
 * StandingsRepository - Data access layer for team standings
 *
 * Retrieves standings data from ibl_standings and ibl_power tables.
 * Supports both conference and division groupings.
 *
 * @phpstan-import-type StandingsRow from StandingsRepositoryInterface
 * @phpstan-import-type StreakRow from StandingsRepositoryInterface
 * @phpstan-import-type PythagoreanStats from StandingsRepositoryInterface
 *
 * @see StandingsRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class StandingsRepository extends \BaseMysqliRepository implements StandingsRepositoryInterface
{
    /**
     * Constructor
     *
     * @param \mysqli $db Active mysqli connection
     */
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    /**
     * Get grouping column names for a region type
     *
     * @param string $region Region name
     * @return array{grouping: string, gbColumn: string, magicNumberColumn: string}
     */
    private function getGroupingColumns(string $region): array
    {
        if (in_array($region, \League::CONFERENCE_NAMES, true)) {
            return [
                'grouping' => 'conference',
                'gbColumn' => 'confGB',
                'magicNumberColumn' => 'confMagicNumber',
            ];
        }

        if (in_array($region, \League::DIVISION_NAMES, true)) {
            return [
                'grouping' => 'division',
                'gbColumn' => 'divGB',
                'magicNumberColumn' => 'divMagicNumber',
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
            s.tid,
            s.team_name,
            s.leagueRecord,
            s.pct,
            s.{$columns['gbColumn']} AS gamesBack,
            s.confRecord,
            s.divRecord,
            s.homeRecord,
            s.awayRecord,
            s.gamesUnplayed,
            s.{$columns['magicNumberColumn']} AS magicNumber,
            s.clinchedConference,
            s.clinchedDivision,
            s.clinchedPlayoffs,
            (s.homeWins + s.homeLosses) AS homeGames,
            (s.awayWins + s.awayLosses) AS awayGames,
            t.color1,
            t.color2
            FROM ibl_standings s
            JOIN ibl_team_info t ON s.tid = t.teamid
            WHERE s.{$columns['grouping']} = ?
            ORDER BY s.{$columns['gbColumn']} ASC";

        /** @var list<StandingsRow> */
        return $this->fetchAll($query, "s", $region);
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
            "SELECT last_win, last_loss, streak_type, streak, ranking FROM ibl_power WHERE TeamID = ?",
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
            FROM ibl_team_offense_stats tos
            JOIN ibl_team_defense_stats tds ON tos.teamID = tds.teamID AND tos.season_year = tds.season_year
            WHERE tos.teamID = ? AND tos.season_year = ?",
            "ii",
            $teamId,
            $seasonYear
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
        /** @var list<array{TeamID: int, last_win: int, last_loss: int, streak_type: string, streak: int, ranking: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT TeamID, last_win, last_loss, streak_type, streak, ranking FROM ibl_power",
            ""
        );

        /** @var array<int, StreakRow> $result */
        $result = [];
        foreach ($rows as $row) {
            $result[$row['TeamID']] = [
                'last_win' => $row['last_win'],
                'last_loss' => $row['last_loss'],
                'streak_type' => $row['streak_type'],
                'streak' => $row['streak'],
                'ranking' => $row['ranking'],
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
        /** @var list<array{teamID: int, off_fgm: int, off_ftm: int, off_tgm: int, def_fgm: int, def_ftm: int, def_tgm: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT
                tos.teamID,
                tos.fgm AS off_fgm, tos.ftm AS off_ftm, tos.tgm AS off_tgm,
                tds.fgm AS def_fgm, tds.ftm AS def_ftm, tds.tgm AS def_tgm
            FROM ibl_team_offense_stats tos
            JOIN ibl_team_defense_stats tds ON tos.teamID = tds.teamID AND tos.season_year = tds.season_year
            WHERE tos.season_year = ?",
            "i",
            $seasonYear
        );

        /** @var array<int, PythagoreanStats> $result */
        $result = [];
        foreach ($rows as $row) {
            $result[$row['teamID']] = $this->calculatePythagoreanStats($row);
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
}
