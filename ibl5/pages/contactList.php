<?php

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

global $leagueContext;
$teamInfoTable = isset($leagueContext) ? $leagueContext->getTableName('ibl_team_info') : 'ibl_team_info';

$query = "SELECT * FROM {$teamInfoTable} ORDER BY team_city ASC";
$result = $mysqli_db->query($query);

echo "<html>
<head>
    <title>IBL GM Contact List</title>
</head>
<body>
    <center>
        <h2>IBL GM Contact List</h2>
        Click on a team name to access that team's page; click on a GM's name to e-mail the GM.<br>
        <table cellpadding=\"1\" border=\"1\">
            <tr>
                <th>Team</th>
                <th>GM's Name</th>
                <th>AIM</th>
                <th>Skype</th>
            </tr>
";

while ($row = $result->fetch_assoc()) {
    $tid = $row["teamid"] ?? '';
    $teamcity = $row["team_city"] ?? '';
    $teamname = $row["team_name"] ?? '';
    $color1 = $row["color1"] ?? '';
    $color2 = $row["color2"] ?? '';
    $owner = $row["owner_name"] ?? '';
    $email = $row["owner_email"] ?? '';
    $skype = $row["skype"] ?? '';
    $aim = $row["aim"] ?? '';
    $msn = $row["msn"] ?? '';

    echo "<tr>
    <td style=\"background-color: #$color1; text-align: center;\">
        <a href=\"team.php?tid=$tid\" style=\"color: #$color2; text-decoration: none; font-weight: bold;\">
            $teamcity $teamname
        </a>
    </td>
    <td style=\"text-align: center;\">
        <a href=\"mailto:$email\" style=\"color: #333; text-decoration: underline;\">
            $owner
        </a>
    </td>
    <td style=\"text-align: center;\">$aim</td>
    <td style=\"text-align: center;\">$skype</td>
</tr>";
}

echo "</TR></TABLE>
</BODY></HTML>";
