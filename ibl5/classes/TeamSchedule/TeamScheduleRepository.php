<?php

declare(strict_types=1);

namespace TeamSchedule;

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
    /**
     * @see TeamScheduleRepositoryInterface::getSchedule()
     *
     * @return list<ScheduleRow>
     */
    public function getSchedule(int $teamID): array
    {
        /** @var list<ScheduleRow> */
        return $this->fetchAll(
            "SELECT s.*, bst.gameOfThatDay
            FROM ibl_schedule s
            LEFT JOIN (
                SELECT Date, visitorTeamID, homeTeamID, MIN(gameOfThatDay) AS gameOfThatDay
                FROM ibl_box_scores_teams
                GROUP BY Date, visitorTeamID, homeTeamID
            ) bst ON bst.Date = s.Date AND bst.visitorTeamID = s.Visitor AND bst.homeTeamID = s.Home
            WHERE s.Visitor = ? OR s.Home = ?
            ORDER BY s.Date ASC",
            'ii',
            $teamID,
            $teamID
        );
    }

    /**
     * @see TeamScheduleRepositoryInterface::getProjectedGamesNextSimResult()
     *
     * @return list<ProjectedGameRow>
     */
    public function getProjectedGamesNextSimResult(int $teamID, string $lastSimEndDate, string $projectedNextSimEndDate): array
    {
        /** @var list<ProjectedGameRow> */
        return $this->fetchAll(
            "SELECT * FROM `ibl_schedule`
             WHERE (Visitor = ? OR Home = ?)
               AND Date BETWEEN ADDDATE(?, 1) AND ?
             ORDER BY Date ASC",
            'iiss',
            $teamID,
            $teamID,
            $lastSimEndDate,
            $projectedNextSimEndDate
        );
    }
}
