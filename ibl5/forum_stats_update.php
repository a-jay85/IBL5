<?php

require 'config.php';
mysql_connect($dbhost, $dbuname, $dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

// Update teams' forum info block with their leading scorer's name
$query13 = "UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.ibl_plr
    SET iblhoops_iblv2forums.forum_stats.pts_lead = (SELECT name FROM iblhoops_ibl5.ibl_plr WHERE iblhoops_iblv2forums.forum_stats.teamname = iblhoops_ibl5.ibl_plr.teamname ORDER BY ((stats_fgm-stats_3gm) * 2 + stats_3gm * 3 +stats_ftm) / stats_gm DESC LIMIT 1)";
$result13 = $db->sql_query($query13);

// Update teams' forum info block with their leading scorer's average points per game
$query14 = "UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.ibl_plr
    SET iblhoops_iblv2forums.forum_stats.pts_num = (SELECT round(((stats_fgm - stats_3gm) * 2 + stats_3gm * 3 + stats_ftm) / stats_gm, 1) FROM iblhoops_ibl5.ibl_plr WHERE iblhoops_iblv2forums.forum_stats.teamname = iblhoops_ibl5.ibl_plr.teamname ORDER BY ((stats_fgm-stats_3gm) * 2 + stats_3gm * 3 + stats_ftm) / stats_gm DESC LIMIT 1)";
$result14 = $db->sql_query($query14);

// Update teams' forum info block with their leading scorer's player id
$query15 = "UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.ibl_plr
    SET iblhoops_iblv2forums.forum_stats.pts_pid = (SELECT pid FROM iblhoops_ibl5.ibl_plr WHERE iblhoops_iblv2forums.forum_stats.teamname = iblhoops_ibl5.ibl_plr.teamname ORDER BY ((stats_fgm - stats_3gm) * 2 + stats_3gm * 3 + stats_ftm) / stats_gm DESC LIMIT 1)";
$result15 = $db->sql_query($query15);

// Update teams' forum info block with their leading rebounder's name
$query16 = "UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.ibl_plr
    SET iblhoops_iblv2forums.forum_stats.reb_lead = (SELECT name FROM iblhoops_ibl5.ibl_plr WHERE iblhoops_iblv2forums. forum_stats.teamname = iblhoops_ibl5.ibl_plr.teamname ORDER BY (stats_orb+stats_drb)/stats_gm DESC LIMIT 1)";
$result16 = $db->sql_query($query16);

// Update teams' forum info block with their leading rebounder's average rebounds per game
$query17 = "UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.ibl_plr
    SET iblhoops_iblv2forums.forum_stats.reb_num = (select round((stats_orb+stats_drb)/stats_gm, 1) FROM iblhoops_ibl5.ibl_plr WHERE iblhoops_iblv2forums. forum_stats.teamname = iblhoops_ibl5.ibl_plr.teamname ORDER BY (stats_orb+stats_drb)/stats_gm DESC LIMIT 1)";
$result17 = $db->sql_query($query17);

// Update teams' forum info block with their leading rebounder's player id
$query18 = "UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.ibl_plr
    SET iblhoops_iblv2forums.forum_stats.reb_pid = (SELECT pid from iblhoops_ibl5.ibl_plr WHERE iblhoops_iblv2forums. forum_stats.teamname = iblhoops_ibl5.ibl_plr.teamname ORDER BY (stats_orb+stats_drb)/stats_gm DESC LIMIT 1)";
$result18 = $db->sql_query($query18);

// Update teams' forum info block with their leading assister's name
$query20 = "UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.ibl_plr
    SET iblhoops_iblv2forums.forum_stats.ast_lead = (SELECT name FROM iblhoops_ibl5.ibl_plr WHERE iblhoops_iblv2forums. forum_stats.teamname = iblhoops_ibl5.ibl_plr.teamname ORDER BY (stats_ast/stats_gm) DESC LIMIT 1)";
$result20 = $db->sql_query($query20);

// Update teams' forum info block with their leading assister's average assists per game
$query21 = "UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.ibl_plr
    SET iblhoops_iblv2forums.forum_stats.ast_num = (select round((stats_ast)/stats_gm, 1) FROM iblhoops_ibl5.ibl_plr WHERE iblhoops_iblv2forums. forum_stats.teamname = iblhoops_ibl5.ibl_plr.teamname ORDER BY stats_ast/stats_gm DESC LIMIT 1)";
$result21 = $db->sql_query($query21);

// Update teams' forum info block with their leading assister's player id
$query22 = "UPDATE iblhoops_iblv2forums.forum_stats, iblhoops_ibl5.ibl_plr
    SET iblhoops_iblv2forums.forum_stats.ast_pid = (SELECT pid FROM iblhoops_ibl5.ibl_plr WHERE iblhoops_iblv2forums. forum_stats.teamname = iblhoops_ibl5.ibl_plr.teamname ORDER BY (stats_ast/stats_gm) DESC LIMIT 1)";
$result22 = $db->sql_query($query22);
