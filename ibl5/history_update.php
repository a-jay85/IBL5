<?php

require 'mainfile.php';

$queryi = "UPDATE ibl_team_history SET div_titles = (SELECT COUNT(*) FROM nuke_ibl_teamawards WHERE nuke_ibl_teamawards.Award like '%Div.%' and ibl_team_history.team_name = nuke_ibl_teamawards.name)";
$resulti = $db->sql_query($queryi);

$queryj = "UPDATE ibl_team_history SET conf_titles = (SELECT COUNT(*) FROM nuke_ibl_teamawards WHERE nuke_ibl_teamawards.Award like '%Conf.%' and ibl_team_history.team_name = nuke_ibl_teamawards.name)";
$resultj = $db->sql_query($queryj);

$queryk = "UPDATE ibl_team_history SET ibl_titles = (SELECT COUNT(*) FROM nuke_ibl_teamawards WHERE nuke_ibl_teamawards.Award like '%World%' and ibl_team_history.team_name = nuke_ibl_teamawards.name)";
$resultk = $db->sql_query($queryk);

$queryl = "UPDATE ibl_team_history SET heat_titles = (SELECT COUNT(*) FROM nuke_ibl_teamawards WHERE nuke_ibl_teamawards.Award like '%H.E.A.T.%' and ibl_team_history.team_name = nuke_ibl_teamawards.name)";
$resultl = $db->sql_query($queryl);

$querym = "UPDATE ibl_team_history SET playoffs = (SELECT COUNT(*) FROM ibl_playoff_results WHERE ibl_playoff_results.winner = ibl_team_history.team_name and ibl_playoff_results.round = '1' or ibl_playoff_results.loser = ibl_team_history.team_name and ibl_playoff_results.round = '1' )";
$resultm = $db->sql_query($querym);

echo "Franchise History update is complete!<br>";
