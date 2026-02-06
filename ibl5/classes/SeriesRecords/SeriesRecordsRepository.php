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
        /** @var list<array{self: int, opponent: int, wins: int, losses: int}> */
        return $this->fetchAll(
            "SELECT self, opponent, wins, losses FROM vw_series_records ORDER BY self, opponent"
        );
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
