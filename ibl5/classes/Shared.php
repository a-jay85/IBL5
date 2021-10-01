<?php
class Shared
{
    public static function getLastSimDatesArray()
    {
        $queryLastSimDates = mysql_query("SELECT *
            FROM ibl_sim_dates
            ORDER BY sim DESC
            LIMIT 1");

        return mysql_fetch_assoc($queryLastSimDates);
    }

    public static function getCurrentSeasonEndingYear()
    {
        $queryCurrentSeasonEndingYear = mysql_query("SELECT value
            FROM nuke_ibl_settings
            WHERE name = 'Current Season Ending Year'
            LIMIT 1");

        return mysql_result($queryCurrentSeasonEndingYear, 0);
    }

    public static function getCurrentSeasonPhase()
    {
        $queryCurrentSeasonPhase = mysql_query("SELECT value
            FROM nuke_ibl_settings
            WHERE name = 'Current Season Phase'
            LIMIT 1");

        return mysql_result($queryCurrentSeasonPhase, 0);
    }

    public static function getTeamnameFromTid($tid)
    {
    	$queryTeamnameFromTid = mysql_query("SELECT team_name
            FROM nuke_ibl_team_info
            WHERE teamid = $tid
            LIMIT 1;");

        return mysql_result($queryTeamnameFromTid, 0);
    }

    public static function getTidFromTeamname($teamname)
    {
        $queryTidFromTeamname = mysql_query("SELECT teamid
            FROM nuke_ibl_team_info
            WHERE team_name = '$teamname'
            LIMIT 1;");

        return mysql_result($queryTidFromTeamname, 0);
    }

    public static function getWaiverWireStatus()
    {
        $queryWaiverWireStatus = mysql_query("SELECT value
            FROM nuke_ibl_settings
            WHERE name = 'Allow Waiver Moves'
            LIMIT 1");

        return mysql_result($queryWaiverWireStatus, 0);
    }

    public static function getAllowTradesStatus()
    {
        $queryAllowTradesStatus = mysql_query("SELECT value
            FROM nuke_ibl_settings
            WHERE name = 'Allow Trades'
            LIMIT 1");

        return mysql_result($queryAllowTradesStatus, 0);
    }
}
