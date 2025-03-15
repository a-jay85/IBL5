<?php

namespace Schedule;

class TeamSchedule extends Schedule
{
    public static function getSchedule($db, $teamID)
    {
        $query = "SELECT *
            FROM `ibl_schedule`
            WHERE Visitor = $teamID
               OR Home = $teamID
            ORDER BY Date ASC;";
        return $db->sql_query($query);
    }

    public static function getProjectedGamesNextSimResult($db, $teamID, $lastSimEndDate)
    {
        $query = "SELECT *
            FROM `ibl_schedule`
            WHERE (Visitor = $teamID OR Home = $teamID)
              AND Date BETWEEN ADDDATE('$lastSimEndDate', 1) AND ADDDATE('$lastSimEndDate', " . \League::getSimLengthInDays($db) . ")
            ORDER BY Date ASC;";
        return $db->sql_query($query);
    }
}