<?php

declare(strict_types=1);

namespace SeriesRecords;

use SeriesRecords\Contracts\SeriesRecordsRepositoryInterface;

/**
 * SeriesRecordsRepository - Data access for series records
 * 
 * Handles all database operations for head-to-head series records
 * between teams. Uses prepared statements via BaseMysqliRepository.
 * 
 * @see SeriesRecordsRepositoryInterface
 * @extends \BaseMysqliRepository
 */
class SeriesRecordsRepository extends \BaseMysqliRepository implements SeriesRecordsRepositoryInterface
{
    public function __construct(object $db)
    {
        parent::__construct($db);
    }

    /**
     * @see SeriesRecordsRepositoryInterface::getTeamsForSeriesRecords()
     */
    public function getTeamsForSeriesRecords(): array
    {
        return $this->fetchAll(
            "SELECT teamid, team_city, team_name, color1, color2
             FROM ibl_team_info
             WHERE teamid != 99 AND teamid != ?
             ORDER BY teamid ASC",
            "i",
            \League::FREE_AGENTS_TEAMID
        );
    }

    /**
     * @see SeriesRecordsRepositoryInterface::getSeriesRecords()
     */
    public function getSeriesRecords(): array
    {
        // This complex query aggregates wins and losses for each team pairing
        // from both home and visitor perspectives
        $query = "SELECT self, opponent, SUM(wins) AS wins, SUM(losses) AS losses
                  FROM (
                      SELECT home AS self, visitor AS opponent, COUNT(*) AS wins, 0 AS losses
                      FROM ibl_schedule
                      WHERE HScore > VScore
                      GROUP BY self, opponent

                      UNION ALL

                      SELECT visitor AS self, home AS opponent, COUNT(*) AS wins, 0 AS losses
                      FROM ibl_schedule
                      WHERE VScore > HScore
                      GROUP BY self, opponent

                      UNION ALL

                      SELECT home AS self, visitor AS opponent, 0 AS wins, COUNT(*) AS losses
                      FROM ibl_schedule
                      WHERE HScore < VScore
                      GROUP BY self, opponent

                      UNION ALL

                      SELECT visitor AS self, home AS opponent, 0 AS wins, COUNT(*) AS losses
                      FROM ibl_schedule
                      WHERE VScore < HScore
                      GROUP BY self, opponent
                  ) t
                  GROUP BY self, opponent
                  ORDER BY self, opponent";

        return $this->fetchAll($query);
    }

    /**
     * @see SeriesRecordsRepositoryInterface::getMaxTeamId()
     */
    public function getMaxTeamId(): int
    {
        $result = $this->fetchOne("SELECT MAX(Visitor) as max_visitor FROM ibl_schedule");
        return $result ? (int) $result['max_visitor'] : 0;
    }
}
