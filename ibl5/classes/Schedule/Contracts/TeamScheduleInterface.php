<?php

namespace Schedule\Contracts;

/**
 * TeamScheduleInterface - Team schedule data retrieval
 *
 * Provides static methods for retrieving team schedule information
 * from the database.
 */
interface TeamScheduleInterface
{
    /**
     * Get full season schedule for a team
     *
     * Retrieves all games (home and away) for a team ordered by date.
     *
     * @param object $db Database connection
     * @param int $teamID Team ID to get schedule for
     * @return mixed Query result resource containing schedule rows
     *
     * **Query Details:**
     * - Table: ibl_schedule
     * - Condition: Visitor = teamID OR Home = teamID
     * - Order: Date ASC
     *
     * **Returned Columns:**
     * All columns from ibl_schedule including Date, Visitor, Home, etc.
     */
    public static function getSchedule($db, int $teamID);

    /**
     * Get projected games for next simulation result
     *
     * Retrieves games scheduled within the next sim period
     * starting from the day after the last sim end date.
     *
     * @param object $db Database connection
     * @param int $teamID Team ID to get schedule for
     * @param string $lastSimEndDate Date string (YYYY-MM-DD) of last sim end
     * @return mixed Query result resource containing upcoming game rows
     *
     * **Query Details:**
     * - Table: ibl_schedule
     * - Condition: Team matches AND Date between (lastSimEndDate+1) and (lastSimEndDate + simLength)
     * - Order: Date ASC
     *
     * **Behaviors:**
     * - Uses League::getSimLengthInDays() to determine sim period length
     * - ADDDATE function used for date arithmetic
     */
    public static function getProjectedGamesNextSimResult($db, int $teamID, string $lastSimEndDate);
}
