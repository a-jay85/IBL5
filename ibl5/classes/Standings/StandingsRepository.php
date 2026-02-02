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
 * @see StandingsRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class StandingsRepository extends \BaseMysqliRepository implements StandingsRepositoryInterface
{
    /**
     * Constructor
     *
     * @param object $db Active mysqli connection
     */
    public function __construct(object $db)
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

        return $this->fetchAll($query, "s", $region);
    }

    /**
    * @see StandingsRepositoryInterface::getTeamStreakData()
     */
    public function getTeamStreakData(int $teamId): ?array
    {
        return $this->fetchOne(
            "SELECT last_win, last_loss, streak_type, streak, ranking FROM ibl_power WHERE TeamID = ?",
            "i",
            $teamId
        );
    }

    /**
     * @see StandingsRepositoryInterface::getTeamPythagoreanStats()
     */
    public function getTeamPythagoreanStats(int $teamId): ?array
    {
        // Get points scored from offense stats
        $offenseStats = $this->fetchOne(
            "SELECT fgm, ftm, tgm FROM ibl_team_offense_stats WHERE teamID = ?",
            "i",
            $teamId
        );

        // Get points allowed from defense stats
        $defenseStats = $this->fetchOne(
            "SELECT fgm, ftm, tgm FROM ibl_team_defense_stats WHERE teamID = ?",
            "i",
            $teamId
        );

        if ($offenseStats === null || $defenseStats === null) {
            return null;
        }

        // Calculate points using BasketballStats\StatsFormatter
        $pointsScored = \BasketballStats\StatsFormatter::calculatePoints(
            $offenseStats['fgm'],
            $offenseStats['ftm'],
            $offenseStats['tgm']
        );

        $pointsAllowed = \BasketballStats\StatsFormatter::calculatePoints(
            $defenseStats['fgm'],
            $defenseStats['ftm'],
            $defenseStats['tgm']
        );

        return [
            'pointsScored' => $pointsScored,
            'pointsAllowed' => $pointsAllowed,
        ];
    }
}
