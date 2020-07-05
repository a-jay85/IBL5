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
</FORM>\n";

if (isset($_POST['query'])) {
    switch ($_POST[query]) {
        case 'Reset All Contract Extensions':
            $queryString = "UPDATE nuke_ibl_team_info SET Used_Extension_This_Season = 0;";
            $logText = "All teams' contract extensions have been reset.";
            break;
        case 'Reset All MLEs/LLEs':
            $queryString = "UPDATE nuke_ibl_team_info SET HasMLE = 1, HasLLE = 1;";
            $logText = "All teams' MLEs and LLEs have been reset.";
            break;
    }

    if (mysql_query($queryString)) {
        echo $queryString . "\n";
        echo "<p>\n";
        echo "<b>" . $logText . "</b>";
    };
}

echo "
</BODY>
</HTML>";

?>
