<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

echo "
<HTML>
<HEAD>
    <TITLE>IBLv5 Control Panel</TITLE>
</HEAD>
<BODY>";

echo "<FORM action=\"leagueControlPanel.php\" method=\"POST\">
    <INPUT type='submit' name='query' value='Reset All Contract Extensions'><p>
    <INPUT type='submit' name='query' value='Reset All MLEs/LLEs'><p>
    <INPUT type='submit' name='query' value='Set all undefined player positions'><p>
</FORM>\n";

if (isset($_POST['query'])) {
    switch ($_POST[query]) {
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
    }

    if (mysql_query($queryString)) {
        echo "<code>" . $queryString . "</code>\n";
        echo "<p>\n";
        echo "<b>" . $successText . "</b>";
    } else {
        echo "Oops, something went wrong. Let A-Jay know what you were trying to do and he'll look into it.";
    };
}

echo "
</BODY>
</HTML>";

?>