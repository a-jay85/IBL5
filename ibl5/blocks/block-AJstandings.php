<?php

if (!defined('BLOCK_FILE')) {
    Header("Location: ../index.php");
    die();
}

global $mysqli_db, $leagueContext;
$season = new Season($mysqli_db);

// Multi-league support: use $leagueContext to get the appropriate standings table
$standingsTable = isset($leagueContext) ? $leagueContext->getTableName('ibl_standings') : 'ibl_standings';

$content .= "
    <center>
        <u>
            Recent Sim Dates:
        </u>
        <br>
        <strong>
            $season->lastSimStartDate
        </strong>
        <br>
        -to-
        <br>
        <strong>
            $season->lastSimEndDate
        </strong>
        <table style=\"width:150px;\">
            <tr>
                <td colspan=3>
                    <hr>
                </td>
            </tr>";

$queryEasternConference = "SELECT tid, team_name, leagueRecord, confGB, clinchedConference, clinchedDivision, clinchedPlayoffs
    FROM $standingsTable
    WHERE conference = 'Eastern'
    ORDER BY confGB ASC";
$resultEasternConference = $mysqli_db->query($queryEasternConference);

$content .= "
    <tr>
        <td colspan=3>
            <center><font color=#fd004d><b>Eastern Conference</b></font></center>
        </td>
    </tr>
    <tr bgcolor=#006cb3>
        <td>
            <center><font color=#ffffff><b>Team</b></font></center>
        </td>
        <td>
            <center><font color=#ffffff><b>W-L</b></font></center>
        </td>
        <td>
            <center><font color=#ffffff><b>GB</b></font></center>
        </td>
    </tr>";

while ($row = $resultEasternConference->fetch_assoc()) {
    $tid = $row['tid'];
    $team_name = trim($row['team_name']);
    $leagueRecord = $row['leagueRecord'];
    $confGB = $row['confGB'];
    $clinchedConference = $row['clinchedConference'];
    $clinchedDivision = $row['clinchedDivision'];
    $clinchedPlayoffs = $row['clinchedPlayoffs'];
    if ($clinchedConference == 1) {
        $team_name = "<b>Z</b>-" . $team_name;
    } elseif ($clinchedDivision == 1) {
        $team_name = "<b>Y</b>-" . $team_name;
    } elseif ($clinchedPlayoffs == 1) {
        $team_name = "<b>X</b>-" . $team_name;
    }

$content .= "
    <tr>
        <td style=\"white-space: nowrap;\">
            <a href=\"modules.php?name=Team&op=team&teamID=$tid\">$team_name</a>
        </td>
        <td style=\"text-align: left;\">
            $leagueRecord
        </td>
        <td style=\"text-align: right;\">
            $confGB
        </td>
    </tr>";
}

$queryWesternConference = "SELECT tid, team_name, leagueRecord, confGB, clinchedConference, clinchedDivision, clinchedPlayoffs
    FROM $standingsTable
    WHERE conference = 'Western'
    ORDER BY confGB ASC";
$resultWesternConference = $mysqli_db->query($queryWesternConference);

$content .= "
    <tr>
        <td colspan=3>
            <hr>
        </td>
    </tr>
    <tr>
        <td colspan=3>
            <center><font color=#fd004d><b>Western Conference</b></font></center>
        </td>
    </tr>
    <tr bgcolor=#006cb3>
        <td>
            <center><font color=#ffffff><b>Team</b></font></center>
        </td>
        <td>
            <center><font color=#ffffff><b>W-L</b></font></center>
        </td>
        <td>
            <center><font color=#ffffff><b>GB</b></font></center>
        </td>
    </tr>";

while ($row = $resultWesternConference->fetch_assoc()) {
    $tid = $row['tid'];
    $team_name = trim($row['team_name']);
    $leagueRecord = $row['leagueRecord'];
    $confGB = $row['confGB'];
    $clinchedConference = $row['clinchedConference'];
    $clinchedDivision = $row['clinchedDivision'];
    $clinchedPlayoffs = $row['clinchedPlayoffs'];
    if ($clinchedConference == 1) {
        $team_name = "<b>Z</b>-" . $team_name;
    } elseif ($clinchedDivision == 1) {
        $team_name = "<b>Y</b>-" . $team_name;
    } elseif ($clinchedPlayoffs == 1) {
        $team_name = "<b>X</b>-" . $team_name;
    }

    $content .= "
        <tr>
            <td style=\"white-space: nowrap; width: 10px;\">
                <a href=\"modules.php?name=Team&op=team&teamID=$tid\">$team_name</a>
            </td>
            <td style=\"text-align: left;\">
                $leagueRecord
            </td>
            <td style=\"text-align: right;\">
                $confGB
            </td>
        </tr>";
}

$content .= "
    <tr>
        <td colspan=3>
            <center><a href=\"modules.php?name=Content&pa=showpage&pid=4\"><font color=#aaaaaa><i>-- Full Standings --</i></font></a></center>
        </td>
    </tr>
</table>";
