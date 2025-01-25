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
              AND Date BETWEEN '$lastSimEndDate' AND ADDDATE('$lastSimEndDate', " . \Sim::LENGTH_IN_DAYS . ")
            ORDER BY Date ASC;";
        return $db->sql_query($query);
    }
}