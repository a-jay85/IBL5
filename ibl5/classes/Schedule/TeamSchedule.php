<?php

declare(strict_types=1);

namespace Schedule;

use Schedule\Contracts\TeamScheduleInterface;

/**
 * @see TeamScheduleInterface
 */
class TeamSchedule extends Schedule implements TeamScheduleInterface
{
    /**
     * @see TeamScheduleInterface::getSchedule()
     */
    public static function getSchedule($db, int $teamID)
    {
        $query = "SELECT *
            FROM `ibl_schedule`
            WHERE Visitor = $teamID
               OR Home = $teamID
            ORDER BY Date ASC;";
        return $db->sql_query($query);
    }

    /**
     * @see TeamScheduleInterface::getProjectedGamesNextSimResult()
     */
    public static function getProjectedGamesNextSimResult($db, int $teamID, string $lastSimEndDate)
    {
        $query = "SELECT *
            FROM `ibl_schedule`
            WHERE (Visitor = $teamID OR Home = $teamID)
              AND Date BETWEEN ADDDATE('$lastSimEndDate', 1) AND ADDDATE('$lastSimEndDate', " . \League::getSimLengthInDays($db) . ")
            ORDER BY Date ASC;";
        return $db->sql_query($query);
    }
}