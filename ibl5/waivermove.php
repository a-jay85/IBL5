<?php

require 'mainfile.php';

$Team_Offering = $_POST['Team_Name'];
$Fields_Counter = $_POST['counterfields'];
$Roster_Slots = $_POST['rosterslots'];
$Healthy_Roster_Slots = $_POST['healthyrosterslots'];
$Type_Of_Action = $_POST['Action'];

$queryt = "SELECT * FROM ibl_team_info WHERE team_name = '$Team_Offering' ";
$resultt = $db->sql_query($queryt);

$teamid = $db->sql_result($resultt, 0, "teamid");

$Timestamp = intval(time());

// ADD TEAM TOTAL SALARY FOR THIS YEAR

$querysalary = "SELECT * FROM ibl_plr WHERE teamname = '$Team_Offering' AND retired = 0 ";
$results = $db->sql_query($querysalary);
$num = $db->sql_numrows($results);
$z = 0;

while ($z < $num) {
    $cy = $db->sql_result($results, $z, "cy");
    $cyy = "cy$cy";
    $cy2 = $db->sql_result($results, $z, "$cyy");
    $TotalSalary = $TotalSalary + $cy2;
    $z++;
}
//ENT TEAM TOTAL SALARY FOR THIS YEAR

$k = 0;
$Salary = 0;

while ($k < $Fields_Counter) {
    $Type = $_POST['type' . $k];
    $Salary = $_POST['cy' . $k];
    $Index = $_POST['index' . $k];
    $Check = $_POST['check' . $k];
    $queryn = "SELECT * FROM ibl_plr WHERE pid = '$Index' ";
    $resultn = $db->sql_query($queryn);
    $playername = $db->sql_result($resultn, 0, "name");
    $players_team = $db->sql_result($resultn, 0, "tid");

    if ($Check == "on") {
        if ($Type_Of_Action == "drop") {
            if ($Roster_Slots < 4 and $TotalSalary > 7000) {

                echo "You have 12 players and are over $70 mill hard cap.  Therefore you can't drop a player! <br>You will be automatically redirected to <a href=\"http://www.iblhoops.net\">the main IBL page</a> in a moment.  If you are not redirected, click the link.";

            } else {

                $queryi = "UPDATE ibl_plr SET `ordinal` = '1000', `droptime` = '$Timestamp' WHERE `pid` = '$Index' LIMIT 1;";
                $resulti = $db->sql_query($queryi);

                $topicid = 32;
                $storytitle = $Team_Offering . " make waiver cuts";
                $hometext = "The " . $Team_Offering . " cut " . $playername . " to waivers.";

                // ==== PUT ANNOUNCEMENT INTO DATABASE ON NEWS PAGE
                $timestamp = date('Y-m-d H:i:s', time());

                $querycat = "SELECT * FROM nuke_stories_cat WHERE title = 'Waiver Pool Moves'";
                $resultcat = $db->sql_query($querycat);
                $WPMoves = $db->sql_result($resultcat, 0, "counter");
                $catid = $db->sql_result($resultcat, 0, "catid");

                $WPMoves = $WPMoves + 1;

                $querycat2 = "UPDATE nuke_stories_cat SET counter = $WPMoves WHERE title = 'Waiver Pool Moves'";
                $resultcat2 = $db->sql_query($querycat2);

                $querystor = "INSERT INTO nuke_stories (catid,aid,title,time,hometext,topic,informant,counter,alanguage) VALUES ('$catid','Associated Press','$storytitle','$timestamp','$hometext','$topicid','Associated Press','0','english')";
                $resultstor = $db->sql_query($querystor);
                echo "<html><head><title>Waiver Processing</title>
			</head>
			<body>
			Your waiver moves should now be processed.  <br>You will be automatically redirected to <a href=\"http://www.iblhoops.net\">the main IBL page</a> in a moment.  If you are not redirected, click the link.
			</body></html>";
            }

        } else {
            if ($players_team == $teamid) {
                $queryi = "UPDATE ibl_plr SET `ordinal` = '800', `teamname` = '$Team_Offering', `tid` = '$teamid' WHERE `pid` = '$Index' LIMIT 1;";
                $resulti = $db->sql_query($queryi);
                $Roster_Slots++;

                $topicid = 33;
                $storytitle = $Team_Offering . " make waiver additions";
                $hometext = "The " . $Team_Offering . " sign " . $playername . " from waivers.";

                // ==== PUT ANNOUNCEMENT INTO DATABASE ON NEWS PAGE

                $timestamp = date('Y-m-d H:i:s', time());

                $querycat = "SELECT * FROM nuke_stories_cat WHERE title = 'Waiver Pool Moves'";
                $resultcat = $db->sql_query($querycat);
                $WPMoves = $db->sql_result($resultcat, 0, "counter");
                $catid = $db->sql_result($resultcat, 0, "catid");

                $WPMoves = $WPMoves + 1;

                $querycat2 = "UPDATE nuke_stories_cat SET counter = $WPMoves WHERE title = 'Waiver Pool Moves'";
                $resultcat2 = $db->sql_query($querycat2);

                $querystor = "INSERT INTO nuke_stories (catid,aid,title,time,hometext,topic,informant,counter,alanguage) VALUES ('$catid','Associated Press','$storytitle','$timestamp','$hometext','$topicid','Associated Press','0','english')";
                $resultstor = $db->sql_query($querystor);
                echo "<html><head><title>Waiver Processing</title>
			</head>
			<body>
			Your waiver moves should now be processed.  <br>You will be automatically redirected to <a href=\"http://www.iblhoops.net\">the main IBL page</a> in a moment.  If you are not redirected, click the link.
			</body></html>";

            } else {

                if ($Healthy_Roster_Slots < 4 and $TotalSalary + $Salary > 7000) {

                    echo "You have 12 or more healthy players and this signing will put you over $70 million.  Therefore you can not make this signing. <br>You will be automatically redirected to <a href=\"http://www.iblhoops.net\">the main IBL page</a> in a moment.  If you are not redirected, click the link.";

                } elseif ($Healthy_Roster_Slots > 3 and $TotalSalary + $Salary > 7000 and $Salary > 138) {

                    echo "You are over the hard cap and therefore can only sign players who are making veteran minimum contract! <br>You will be automatically redirected to <a href=\"http://www.iblhoops.net\">the main IBL page</a> in a moment.  If you are not redirected, click the link.";

                } elseif ($Healthy_Roster_Slots < 1) {
                    echo "You have full roster of 15 players.  You can't sign another player at this time! <br>You will be automatically redirected to <a href=\"http://www.iblhoops.net\">the main IBL page</a> in a moment.  If you are not redirected, click the link.";

                } else {

                    $queryi = "UPDATE ibl_plr SET `ordinal` = '800', `cy` = '1', `cy1` = '$Salary', `teamname` = '$Team_Offering', `tid` = '$teamid' WHERE `pid` = '$Index' LIMIT 1;";
                    $resulti = $db->sql_query($queryi);
                    $Roster_Slots++;

                    $topicid = 33;
                    $storytitle = $Team_Offering . " make waiver additions";
                    $hometext = "The " . $Team_Offering . " sign " . $playername . " from waivers.";

                    // ==== PUT ANNOUNCEMENT INTO DATABASE ON NEWS PAGE

                    $timestamp = date('Y-m-d H:i:s', time());

                    $querycat = "SELECT * FROM nuke_stories_cat WHERE title = 'Waiver Pool Moves'";
                    $resultcat = $db->sql_query($querycat);
                    $WPMoves = $db->sql_result($resultcat, 0, "counter");
                    $catid = $db->sql_result($resultcat, 0, "catid");

                    $WPMoves = $WPMoves + 1;

                    $querycat2 = "UPDATE nuke_stories_cat SET counter = $WPMoves WHERE title = 'Waiver Pool Moves'";
                    $resultcat2 = $db->sql_query($querycat2);

                    $querystor = "INSERT INTO nuke_stories (catid,aid,title,time,hometext,topic,informant,counter,alanguage) VALUES ('$catid','The Associated Press','$storytitle','$timestamp','$hometext','$topicid','The Associated Press','0','english')";
                    $resultstor = $db->sql_query($querystor);
                    echo "<html><head><title>Waiver Processing</title>
				</head>
				<body>
				Your waiver moves should now be processed.  <br>You will be automatically redirected to <a href=\"http://www.iblhoops.net\">the main IBL page</a> in a moment.  If you are not redirected, click the link.
				</body></html>";

                }
            }
        }
    }
    $k++;
}
