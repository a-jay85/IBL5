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
            tid,
            team_name,
            leagueRecord,
            pct,
            {$columns['gbColumn']} AS gamesBack,
            confRecord,
            divRecord,
            homeRecord,
            awayRecord,
            gamesUnplayed,
            {$columns['magicNumberColumn']} AS magicNumber,
            clinchedConference,
            clinchedDivision,
            clinchedPlayoffs,
            (homeWins + homeLosses) AS homeGames,
            (awayWins + awayLosses) AS awayGames
            FROM ibl_standings
            WHERE {$columns['grouping']} = ?
            ORDER BY {$columns['gbColumn']} ASC";

        return $this->fetchAll($query, "s", $region);
    }

    /**
    * @see StandingsRepositoryInterface::getTeamStreakData()
     */
    public function getTeamStreakData(int $teamId): ?array
    {
        return $this->fetchOne(
            "SELECT last_win, last_loss, streak_type, streak FROM ibl_power WHERE TeamID = ?",
            "i",
            $teamId
        );
    }
}
