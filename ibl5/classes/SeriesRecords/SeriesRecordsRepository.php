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
 */
class SeriesRecordsRepository extends \BaseMysqliRepository implements SeriesRecordsRepositoryInterface
{
    public function __construct(object $db)
    {
        parent::__construct($db);
    }

    /**
     * @see SeriesRecordsRepositoryInterface::getTeamsForSeriesRecords()
     *
     * @return list<array{teamid: int, team_city: string, team_name: string, color1: string, color2: string}>
     */
    public function getTeamsForSeriesRecords(): array
    {
        /** @var list<array{teamid: int, team_city: string, team_name: string, color1: string, color2: string}> */
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
     *
     * @return list<array{self: int, opponent: int, wins: int, losses: int}>
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

        /** @var list<array{self: int, opponent: int, wins: int, losses: int}> */
        return $this->fetchAll($query);
    }

    /**
     * @see SeriesRecordsRepositoryInterface::getMaxTeamId()
     */
    public function getMaxTeamId(): int
    {
        /** @var array{max_visitor: int|null}|null $result */
        $result = $this->fetchOne("SELECT MAX(Visitor) as max_visitor FROM ibl_schedule");
        if ($result === null || $result['max_visitor'] === null) {
            return 0;
        }

        return $result['max_visitor'];
    }
}
