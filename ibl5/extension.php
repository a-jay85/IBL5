<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

require $_SERVER['DOCUMENT_ROOT'] . '/discordWebhooks.php';

echo "<HTML><HEAD><TITLE>Contract Extension Offer Result</TITLE></HEAD><BODY>";

$Team_Name = $_POST['teamname'];
$Player_Name = $_POST['playername'];
$Demands_Years = $_POST['demyrs'];
$Demands_Total = $_POST['demtot'] * 100;
$Cap_Space = $_POST['capnumber'];
$Offer_Max = $_POST['maxyr1'];
$Bird = $_POST['bird'];
$Offer_1 = $_POST['offeryear1'];
$Offer_2 = $_POST['offeryear2'];
$Offer_3 = $_POST['offeryear3'];
$Offer_4 = $_POST['offeryear4'];
$Offer_5 = $_POST['offeryear5'];

$Offer_max_increase = round($Offer_1 * 0.1,0);

if ($Bird > 2) {
    $Offer_max_increase = round($Offer_1 * 0.125,0);
}

//-----GRAB TEAM INFO AND STATS------
$query = "SELECT * FROM nuke_ibl_team_info WHERE team_name = '$Team_Name'";
$result = mysql_query($query);

$tf_wins = mysql_result($result, 0, "Contract_Wins");
$tf_loss = mysql_result($result, 0, "Contract_Losses");
$tf_trdw = mysql_result($result, 0, "Contract_AvgW");
$tf_trdl = mysql_result($result, 0, "Contract_AvgL");
$tf_coach = mysql_result($result, 0, "Contract_Coach");

//echo "Wins: $tf_wins<br> Losses: $tf_loss<br> Trad Win: $tf_trdw<br> Trad Loss: $tf_trdl<br> Coach: $tf_coach<br>";

$UsedExtensionChunk = mysql_result($result, 0, "Used_Extension_This_Chunk");
$UsedExtensionSeason = mysql_result($result, 0, "Used_Extension_This_Season");
//-----END OF TEAM INFO AND STATS-----

//-----GRAB PLAYER PREFERENCES-----
$queryteam = "SELECT * FROM nuke_iblplyr WHERE name = '$Player_Name'";
$resultteam = mysql_query($queryteam);

$player_team = mysql_result($resultteam, 0, "teamname");
$player_winner = mysql_result($resultteam, 0, "winner");
$player_tradition = mysql_result($resultteam, 0, "tradition");
$player_coach = mysql_result($resultteam, 0, "coach");
$player_security = mysql_result($resultteam, 0, "security");
$player_loyalty = mysql_result($resultteam, 0, "loyalty");
$player_playingtime = mysql_result($resultteam, 0, "playingTime");
//-----END OF PLAYER PREFERENCES-----

$nooffer = 0;

// ==== CHECK FOR ILLEGAL OFFERS WITH ZERO CONTRACT AMOUNTS ====

if ($Offer_1 == 0) {
    echo "Sorry, you must enter an amount greater than zero for each of the first three contract years when making an extension offer.  Your offer in Year 1 was zero, so this offer is not valid.<br>";
    $nooffer = 1;
} else if ($Offer_2 == 0) {
    echo "Sorry, you must enter an amount greater than zero for each of the first three contract years when making an extension offer.  Your offer in Year 2 was zero, so this offer is not valid.<br>";
    $nooffer = 1;
} else if ($Offer_3 == 0) {
    echo "Sorry, you must enter an amount greater than zero for each of the first three contract years when making an extension offer.  Your offer in Year 3 was zero, so this offer is not valid.<br>";
    $nooffer = 1;
}

// ==== CHECK FOR ILLEGAL OFFERS THAT ARE OVER THE SALARY CAP
if ($Offer_1 > $Cap_Space) {
    echo "Sorry, you do not have sufficient cap space to make the offer.  You offered $Offer_1 in the first year of the contract, which is more than $Cap_Space, the amount of cap space you have available.<br>";
    $nooffer = 1;
}

// ==== CHECK FOR ILLEGAL OFFERS DUE TO USE OF EXTENSION THIS SEASON
if ($UsedExtensionSeason == 1) {
    echo "Sorry, you have already used your extension for this season.<br>";
    $nooffer = 1;
}

// ==== CHECK FOR OFFERS OVER THE MAX ALLOWED
if ($Offer_1 > $Offer_Max) {
    echo "Sorry, this offer is over the maximum allowed offer for a player with his years of service.<br>";
    $nooffer = 1;
}

// ==== CHECK FOR ILLEGAL OFFERS DUE TO USE OF EXTENSION THIS CHUNK
if ($UsedExtensionChunk == 1) {
    echo "Sorry, you have already used your extension for this Chunk.<br>";
    $nooffer = 1;
}

// ==== CHECK FOR ILLEGAL RAISES

if ($Offer_2 > $Offer_1 + $Offer_max_increase) {
    $legaloffer = $Offer_1 + $Offer_max_increase;
    echo "Sorry, you tried to offer a larger raise than is permitted.  Your first year offer was $Offer_1 which means the maximum raise allowed each year is $Offer_max_increase.  Your offer in Year 2 was $Offer_2, which is more than your Year 1 offer, $Offer_1, plus the max increase of $Offer_max_increase.  Given your offer in Year 1, the most you can offer in Year 2 is $legaloffer.<br>";
    $nooffer = 1;
}
if ($Offer_3 > $Offer_2 + $Offer_max_increase) {
    $legaloffer = $Offer_2 + $Offer_max_increase;
    echo "Sorry, you tried to offer a larger raise than is permitted.  Your first year offer was $Offer_1 which means the maximum raise allowed each year is $Offer_max_increase.  Your offer in Year 3 was $Offer_3, which is more than your Year 2 offer, $Offer_2, plus the max increase of $Offer_max_increase.  Given your offer in Year 2, the most you can offer in Year 3 is $legaloffer.<br>";
    $nooffer = 1;
}
if ($Offer_4 > $Offer_3 + $Offer_max_increase) {
    $legaloffer = $Offer_3 + $Offer_max_increase;
    echo "Sorry, you tried to offer a larger raise than is permitted.  Your first year offer was $Offer_1 which means the maximum raise allowed each year is $Offer_max_increase.  Your offer in Year 4 was $Offer_4, which is more than your Year 3 offer, $Offer_3, plus the max increase of $Offer_max_increase.  Given your offer in Year 3, the most you can offer in Year 4 is $legaloffer.<br>";
    $nooffer = 1;
}
if ($Offer_5 > $Offer_4 + $Offer_max_increase) {
    $legaloffer = $Offer_4 + $Offer_max_increase;
    echo "Sorry, you tried to offer a larger raise than is permitted.  Your first year offer was $Offer_1 which means the maximum raise allowed each year is $Offer_max_increase.  Your offer in Year 5 was $Offer_5, which is more than your Year 4 offer, $Offer_4, plus the max increase of $Offer_max_increase.  Given your offer in Year 4, the most you can offer in Year 5 is $legaloffer.<br>";
    $nooffer = 1;
}

// ==== CHECK FOR ILLEGAL LOWERING OF SALARY
if ($Offer_2 < $Offer_1) {
    if ($Offer_2 == 0) {
    } else {
        echo "Sorry, you cannot decrease salary in later years of a contract.  You offered $Offer_2 in the second year, which is less than you offered in the first year, $Offer_1.<br>";
        $nooffer = 1;
    }
}
if ($Offer_3 < $Offer_2) {
    if ($Offer_3 == 0) {
    } else {
        echo "Sorry, you cannot decrease salary in later years of a contract.  You offered $Offer_3 in the third year, which is less than you offered in the second year, $Offer_2.<br>";
        $nooffer = 1;
    }
}
if ($Offer_4 < $Offer_3) {
    if ($Offer_4 == 0) {
    } else {
        echo "Sorry, you cannot decrease salary in later years of a contract.  You offered $Offer_4 in the fourth year, which is less than you offered in the third year, $Offer_3.<br>";
        $nooffer = 1;
    }
}
if ($Offer_5 < $Offer_4) {
    if ($Offer_5 == 0) {
    } else {
        echo "Sorry, you cannot decrease salary in later years of a contract.  You offered $Offer_5 in the fifth year, which is less than you offered in the fourth year, $Offer_4.<br>";
        $nooffer = 1;
    }
}

// ==== IF OFFER IS LEGIT, PROCESS OFFER ====

if ($nooffer == 0) {

    // ==== MARK THE EXTENSION AS USED FOR THIS CHUNK ====

    $querychunk="UPDATE nuke_ibl_team_info SET Used_Extension_This_Chunk = 1 WHERE team_name = '$Team_Name'";
    $resultchunk=mysql_query($querychunk);

    echo "Message from the commissioner's office: <font color=#0000cc>Your offer is legal, and is therefore an extension attempt.  Please note that you may make no further extension attempts until after the next sim.</font></br>";

    $Offer_Total = 0;
    $Offer_Total = $Offer_Total + $Offer_1;
    $Offer_Total = $Offer_Total + $Offer_2;
    $Offer_Total = $Offer_Total + $Offer_3;
    $Offer_Total = $Offer_Total + $Offer_4;
    $Offer_Total = $Offer_Total + $Offer_5;
    $Offer_Years = 5;
    if ($Offer_5 == 0) {
        $Offer_Years = 4;
    }
    if ($Offer_4 == 0) {
        $Offer_Years = 3;
    }

    $modfactor1 = (0.000153 * ($tf_wins - $tf_loss) * ($player_winner - 1));
    $modfactor2 = (0.000153 * ($tf_trdw - $tf_trdl) * ($player_tradition - 1));
    $modfactor3 = (0.0025 * ($tf_coach) * ($player_coach - 1));
    $modfactor4 = (.025 * ($player_loyalty - 1));
    //$modfactor5 = (.01*($Offer_Years)-0.025)*($player_security-1);
    $modfactor6 = -(.0025 * $tf_millions / 100 - 0.025) * ($player_playingtime - 1);

    $modifier = 1 + $modfactor1 + $modfactor2 + $modfactor4 + $modfactor5 + $modfactor6;

    @$Offer_Value = $Offer_Total / $Offer_Years;

    @$Demands_Value = $Demands_Total / $Demands_Years;

    //echo "Winner: $modfactor1<br> Tradition: $modfactor2<br> Coach: $modfactor3<br> Loyalty: $modfactor4<br> Security: $modfactor5<br> Play Time: $modfactor6<br>Money Commited: $tf_millions<br> Pre Mod Offer: $Offer_Value<br>";
    //echo "Pre Mod Offer: $Offer_Value<br><br>";

    @$Offer_Value = $Offer_Value * $modifier;

    //echo "Mod: $modifier<br>Post Mod Offer: $Offer_Value<br><br>";
    if ($Offer_Value < $Demands_Value) {
        $Offer_in_Millions = $Offer_Total / 100;

        $storytitle = $Player_Name." turns down an extension offer from the ".$Team_Name;
        $hometext = $Player_Name." today rejected a contract extension offer from the ".$Team_Name." worth $Offer_in_Millions million dollars over ".$Offer_Years." years.";

        postToDiscordChannel('#extensions', $hometext);

        echo "<table bgcolor=#cccccc><tr><td><b>Response from $Player_Name:</b> While I appreciate your offer of $Offer_in_Millions million dollars over $Offer_Years years, it kinda sucks, and isn't what I'm looking for. You're gonna have to try harder if you want me to stick around this dump!</td></tr></table>
        Note from the commissioner's office: <font color=#cc0000>Please note that you will be able to make another attempt next Chunk as you have not yet used up your successful extension for this season.</font><br>";

        $recipient = 'ibldepthcharts@gmail.com';
        $emailsubject = "Unsuccessful Extension - " . $Player_Name;
        $filetext = $Player_Name . " refuses an extension offer from the " . $Team_Name . " of " . $Offer_Total . " for " . $Offer_Years." years.
        For reference purposes: the offer was " . $Offer_1 . " " . $Offer_2 . " " . $Offer_3 . " " . $Offer_4 . " " . $Offer_5 . " and the offer value was thus considered to be " . $Offer_Value . "; the player wanted an offer with a value of " . $Demands_Value;

        if (mail($recipient, $emailsubject, $filetext, "From: rejected-extensions@iblhoops.net"))
        {
        }

        $timestamp = date('Y-m-d H:i:s', time());

        $querytopic = "SELECT * FROM nuke_topics WHERE topicname = '$Team_Name'";
        $resulttopic = mysql_query($querytopic);
        $topicid = mysql_result($resulttopic, 0, "topicid");

        $querycat = "SELECT * FROM nuke_stories_cat WHERE title = 'Contract Extensions'";
        $resultcat = mysql_query($querycat);
        $ContractExtensions = mysql_result($resultcat, 0, "counter");
        $catid = mysql_result($resultcat, 0, "catid");

        $ContractExtensions = $ContractExtensions + 1;

        $querycat2 = "UPDATE nuke_stories_cat SET counter = $ContractExtensions WHERE title = 'Contract Extensions'";
        $resultcat2 = mysql_query($querycat2);

        $querystor = "INSERT INTO nuke_stories (catid, aid, title, time, hometext, topic, informant, counter, alanguage)
            VALUES ('$catid', 'Associated Press', '$storytitle', '$timestamp', '$hometext', '$topicid', 'Associated Press', '0', 'english')";
        $resultstor = mysql_query($querystor);

    } else {
        $Offer_in_Millions = $Offer_Total / 100;

        $storytitle = $Player_Name . " extends his contract with the " . $Team_Name;
        $hometext = $Player_Name . " today accepted a contract extension offer from the " . $Team_Name . " worth $Offer_in_Millions million dollars over " . $Offer_Years . " years.";

        postToDiscordChannel('#extensions', $hometext);

        echo "<table bgcolor=#cccccc><tr><td><b>Response from $Player_Name:</b> I accept your extension offer of $Offer_in_Millions million dollars over $Offer_Years years.  Thank you! (Can't believe you gave me that much...sucker!)</td></tr></table>
        Note from the commissioner's office: <font color=#cc0000>Please note that you have used up your successful extension for this season and may not make any more extension attempts.</font><br>";

        $recipient = 'ibldepthcharts@gmail.com';
        $emailsubject = "Successful Extension - " . $Player_Name;
        $filetext = $Player_Name." accepts an extension offer from the " . $Team_Name . " of " . $Offer_Total . " for " . $Offer_Years . " years.
            For reference purposes: the offer was " . $Offer_1 . " " . $Offer_2 . " " . $Offer_3 . " " . $Offer_4 . " " . $Offer_5 . " and the offer value was thus considered to be " . $Offer_Value . "; the player wanted an offer with a value of " . $Demands_Value;

        if (mail($recipient, $emailsubject, $filetext, "From: accepted-extensions@iblhoops.net")) {
            echo "<center> An e-mail regarding this extension has been successfully sent to the commissioner's office.  Thank you. </center>";
        } else {
            echo " Message failed to e-mail properly; please notify the commissioner of the error with the following amounts you offered:</center><br>
            " . $Offer_1 . " " . $Offer_2 . " " . $Offer_3 . " " . $Offer_4 . " " . $Offer_5;
        }

        // ==== UPDATE PLAYER DATABASE WITH NEW CONTRACT INFORMATION ====
        $yearOfCurrentContract = mysql_result($resultteam, 0, "cy");
        $salaryInCurrentYear = mysql_result($resultteam, 0, "cy" . $yearOfCurrentContract);

        $queryContractUpdate = "UPDATE nuke_iblplyr
            SET cy = 1,
                cyt = 1 + $Offer_Years,
                cy1 = $salaryInCurrentYear,
                cy2 = $Offer_1,
                cy3 = $Offer_2,
                cy4 = $Offer_3,
                cy5 = $Offer_4,
                cy6 = $Offer_5
            WHERE name = '$Player_Name';";
        $resultContractUpdate = mysql_query($queryContractUpdate);

        // ==== MARK THE EXTENSION AS USED FOR THIS SEASON ====

        $queryseason = "UPDATE nuke_ibl_team_info SET Used_Extension_This_Season = 1 WHERE team_name = '$Team_Name'";
        $resultseason = mysql_query($queryseason);

        // ==== PUT ANNOUNCEMENT INTO DATABASE ON NEWS PAGE

        $timestamp = date('Y-m-d H:i:s', time());

        $querytopic = "SELECT * FROM nuke_topics WHERE topicname = '$Team_Name'";
        $resulttopic = mysql_query($querytopic);
        $topicid = mysql_result($resulttopic, 0, "topicid");

        $querycat = "SELECT * FROM nuke_stories_cat WHERE title = 'Contract Extensions'";
        $resultcat = mysql_query($querycat);
        $ContractExtensions = mysql_result($resultcat, 0, "counter");
        $catid = mysql_result($resultcat, 0, "catid");

        $ContractExtensions = $ContractExtensions + 1;

        $querycat2 = "UPDATE nuke_stories_cat SET counter = $ContractExtensions WHERE title = 'Contract Extensions'";
        $resultcat2 = mysql_query($querycat2);

        $querystor = "INSERT INTO nuke_stories (catid, aid, title, time, hometext, topic, informant, counter, alanguage)
            VALUES ('$catid', 'Associated Press', '$storytitle', '$timestamp', '$hometext', '$topicid', 'Associated Press', '0', 'english')";
        $resultstor = mysql_query($querystor);

    }

} else {
    echo "<font color=#ff0000>Your extension attempt was not legal and will not be recorded as an attempt. If you have not yet successfully extended a player this season, and have not yet made a successful offer this Chunk, you may press the \"Back\" Button on your browser to try again.</font>";
}

?>
