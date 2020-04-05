<?php

function getLastSimDatesArray()
{
    $queryLastSimDates = mysql_query("SELECT *
        FROM ibl_sim_dates
        ORDER BY sim DESC
        LIMIT 1");

    return mysql_fetch_assoc($queryLastSimDates);
}
