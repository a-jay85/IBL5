<?php

function getLastSimDatesArray()
{
    $queryLastSimDates = mysql_query("SELECT *
        FROM ibl_sim_dates
        ORDER BY sim DESC
        LIMIT 1");

    return mysql_fetch_assoc($queryLastSimDates);
}

function getCurrentSeasonEndingYear()
{
    $queryCurrentSeasonEndingYear = mysql_query("SELECT value
        FROM nuke_ibl_settings
        WHERE name = 'Current Season Ending Year'
        LIMIT 1");

    return mysql_result($queryCurrentSeasonEndingYear, 0);
}

function getCurrentSeasonPhase()
{
    $queryCurrentSeasonPhase = mysql_query("SELECT value
        FROM nuke_ibl_settings
        WHERE name = 'Current Season Phase'
        LIMIT 1");

    return mysql_result($queryCurrentSeasonPhase, 0);
}
