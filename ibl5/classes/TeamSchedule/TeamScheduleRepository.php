<?php

declare(strict_types=1);

namespace TeamSchedule;

use TeamSchedule\Contracts\TeamScheduleRepositoryInterface;

/**
 * TeamScheduleRepository - Database operations for team schedules
 *
 * @see TeamScheduleRepositoryInterface For the interface contract
 */
class TeamScheduleRepository extends \BaseMysqliRepository implements TeamScheduleRepositoryInterface
{
    /**
     * @see TeamScheduleRepositoryInterface::getSchedule()
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSchedule(int $teamID): array
    {
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
     * @return array<int, array<string, mixed>>
     */
    public function getProjectedGamesNextSimResult(int $teamID, string $lastSimEndDate): array
    {
        /** @var \mysqli $db */
        $db = $this->db;
        $league = new \League($db);
        $simLengthInDays = $league->getSimLengthInDays();

        return $this->fetchAll(
            "SELECT * FROM `ibl_schedule`
             WHERE (Visitor = ? OR Home = ?)
               AND Date BETWEEN ADDDATE(?, 1) AND ADDDATE(?, ?)
             ORDER BY Date ASC",
            'iissi',
            $teamID,
            $teamID,
            $lastSimEndDate,
            $lastSimEndDate,
            $simLengthInDays
        );
    }
}
