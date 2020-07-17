<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

require $_SERVER['DOCUMENT_ROOT'] . '/sharedFunctions.php';

$queryString = "";
$successText = "";

if (isset($_POST['query'])) {
    switch ($_POST['query']) {
        case 'Set Season Phase':
            if(isset($_POST['SeasonPhase'])) {
                $queryString = "UPDATE nuke_ibl_settings SET value = '{$_POST['SeasonPhase']}' WHERE name = 'Current Season Phase';";
            }
            $successText = "Season Phase has been set to {$_POST['SeasonPhase']}.";
            break;
        case 'Reset All Contract Extensions':
            $queryString = "UPDATE nuke_ibl_team_info SET Used_Extension_This_Season = 0;";
            $successText = "All teams' contract extensions have been reset.";
            break;
        case 'Reset All MLEs/LLEs':
            $queryString = "UPDATE nuke_ibl_team_info SET HasMLE = 1, HasLLE = 1;";
            $successText = "All teams' MLEs and LLEs have been reset.";
            break;
        case 'Set all undefined player positions':
            $queryString = "UPDATE nuke_iblplyr SET altpos = pos WHERE altpos = \"\"";
            $successText = "All undefined player positions have been set.";
            break;
        case 'Set all players on waivers to Free Agents and reset their Bird years':
            $queryString = "UPDATE nuke_iblplyr SET teamname = 'Free Agents', bird = 0 WHERE retired != 1 AND ordinal >= 960;";
            $successText = "All players currently on waivers have their teamname set to Free Agents and 0 Bird years.";
            break;
    }

    if (mysql_query($queryString)) {
        $querySuccessful = TRUE;
    } else {
        $querySuccessful = FALSE;
    };
}

$currentSeasonPhase = getCurrentSeasonPhase();

echo "
<HTML>
<HEAD>
    <TITLE>IBLv5 Control Panel</TITLE>
</HEAD>
<BODY>";

echo "<FORM action=\"leagueControlPanel.php\" method=\"POST\">
    <select name=\"SeasonPhase\">
        <option value = \"Preseason\"" . ($currentSeasonPhase == "Preseason" ? " SELECTED" : "") . ">Preseason</option>
        <option value = \"HEAT\"" . ($currentSeasonPhase == "HEAT" ? " SELECTED" : "") . ">HEAT</option>
        <option value = \"Regular Season\"" . ($currentSeasonPhase == "Regular Season" ? " SELECTED" : "") . ">Regular Season</option>
        <option value = \"Playoffs\"" . ($currentSeasonPhase == "Playoffs" ? " SELECTED" : "") . ">Playoffs</option>
        <option value = \"Draft\"" . ($currentSeasonPhase == "Draft" ? " SELECTED" : "") . ">Draft</option>
        <option value = \"Free Agency\"" . ($currentSeasonPhase == "Free Agency" ? " SELECTED" : "") . ">Free Agency</option>
    </select>
    <INPUT type='submit' name='query' value='Set Season Phase'><p>
    <INPUT type='submit' name='query' value='Reset All Contract Extensions'><p>
    <INPUT type='submit' name='query' value='Reset All MLEs/LLEs'><p>
    <INPUT type='submit' name='query' value='Set all undefined player positions'><p>
    <INPUT type='submit' name='query' value='Set all players on waivers to Free Agents and reset their Bird years'><p>
</FORM>\n";

if ($querySuccessful = TRUE) {
    echo "<code>" . $queryString . "</code>\n";
    echo "<p>\n";
    echo "<b>" . $successText . "</b>";
} elseif ($querySuccessful = FALSE) {
    echo "Oops, something went wrong. Let A-Jay know what you were trying to do and he'll look into it.";
};

echo "
</BODY>
</HTML>";

?>
