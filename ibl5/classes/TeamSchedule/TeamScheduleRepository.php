<?php

declare(strict_types=1);

namespace TeamSchedule;

use League\LeagueContext;
use TeamSchedule\Contracts\TeamScheduleRepositoryInterface;

/**
 * TeamScheduleRepository - Database operations for team schedules
 *
 * @phpstan-import-type ScheduleRow from TeamScheduleRepositoryInterface
 * @phpstan-import-type ProjectedGameRow from TeamScheduleRepositoryInterface
 *
 * @see TeamScheduleRepositoryInterface For the interface contract
 */
class TeamScheduleRepository extends \BaseMysqliRepository implements TeamScheduleRepositoryInterface
{
    private string $scheduleTable;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->scheduleTable = $this->resolveTable('ibl_schedule');
    }

    /**
     * @see TeamScheduleRepositoryInterface::getSchedule()
     *
     * @return list<ScheduleRow>
     */
    public function getSchedule(int $teamid, int $seasonYear): array
    {
        /** @var list<ScheduleRow> */
        return $this->fetchAll(
            "SELECT s.*, bst.game_of_that_day
            FROM {$this->scheduleTable} s
            LEFT JOIN " . $this->gameOfThatDaySubquery() . " bst ON bst.game_date = s.game_date AND bst.visitor_teamid = s.visitor_teamid AND bst.home_teamid = s.home_teamid
            WHERE s.season_year = ? AND (s.visitor_teamid = ? OR s.home_teamid = ?)
            ORDER BY s.game_date ASC",
            'iii',
            $seasonYear,
            $teamid,
            $teamid
        );
    }

    /**
     * @see TeamScheduleRepositoryInterface::getProjectedGamesNextSimResult()
     *
     * @return list<ProjectedGameRow>
     */
    public function getProjectedGamesNextSimResult(int $teamid, string $lastSimEndDate, string $projectedNextSimEndDate, int $seasonYear): array
    {
        /** @var list<ProjectedGameRow> */
        return $this->fetchAll(
            "SELECT * FROM `{$this->scheduleTable}`
             WHERE season_year = ?
               AND (visitor_teamid = ? OR home_teamid = ?)
               AND game_date BETWEEN ADDDATE(?, 1) AND ?
             ORDER BY game_date ASC",
            'iiiss',
            $seasonYear,
            $teamid,
            $teamid,
            $lastSimEndDate,
            $projectedNextSimEndDate
        );
    }
}
