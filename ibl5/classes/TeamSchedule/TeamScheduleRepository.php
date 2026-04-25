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
    private string $boxScoresTeamsTable;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->scheduleTable = $this->resolveTable('ibl_schedule');
        $this->boxScoresTeamsTable = $this->resolveTable('ibl_box_scores_teams');
    }

    /**
     * @see TeamScheduleRepositoryInterface::getSchedule()
     *
     * @return list<ScheduleRow>
     */
    public function getSchedule(int $teamid): array
    {
        /** @var list<ScheduleRow> */
        return $this->fetchAll(
            "SELECT s.*, bst.game_of_that_day
            FROM {$this->scheduleTable} s
            LEFT JOIN (
                SELECT game_date, visitor_teamid, home_teamid, MIN(game_of_that_day) AS game_of_that_day
                FROM {$this->boxScoresTeamsTable}
                GROUP BY game_date, visitor_teamid, home_teamid
            ) bst ON bst.game_date = s.game_date AND bst.visitor_teamid = s.visitor_teamid AND bst.home_teamid = s.home_teamid
            WHERE s.visitor_teamid = ? OR s.home_teamid = ?
            ORDER BY s.game_date ASC",
            'ii',
            $teamid,
            $teamid
        );
    }

    /**
     * @see TeamScheduleRepositoryInterface::getProjectedGamesNextSimResult()
     *
     * @return list<ProjectedGameRow>
     */
    public function getProjectedGamesNextSimResult(int $teamid, string $lastSimEndDate, string $projectedNextSimEndDate): array
    {
        /** @var list<ProjectedGameRow> */
        return $this->fetchAll(
            "SELECT * FROM `{$this->scheduleTable}`
             WHERE (visitor_teamid = ? OR home_teamid = ?)
               AND game_date BETWEEN ADDDATE(?, 1) AND ?
             ORDER BY game_date ASC",
            'iiss',
            $teamid,
            $teamid,
            $lastSimEndDate,
            $projectedNextSimEndDate
        );
    }
}
