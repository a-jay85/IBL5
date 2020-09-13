<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2002 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if (!eregi("modules.php", $_SERVER['PHP_SELF'])) die ("You can't access this file directly...");

require_once("mainfile.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/sharedFunctions.php';

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$userpage = 1;

function userinfo($username, $bypass = 0, $hid = 0, $url = 0)
{
	global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $useset, $subscription_url;
	$sql = "SELECT * FROM " . $prefix . "_bbconfig";
	$result = $db->sql_query($sql);
	while ($row = $db->sql_fetchrow($result)) {
		$board_config[$row['config_name']] = $row['config_value'];
	}
	$sql2 = "SELECT * FROM " . $user_prefix . "_users WHERE username = '$username'";
	$result2 = $db->sql_query($sql2);
	$userinfo = $db->sql_fetchrow($result2);
	if (!$bypass) {
		cookiedecode($user);
	}

	$teamlogo = $userinfo[user_ibl_team];
	$tid = getTidFromTeamname($teamlogo);

	include("header.php");
	OpenTable();
	displaytopmenu($tid);



	CloseTable();
	include("footer.php");
}

function main($user) {
	global $stop;
	if (!is_user($user)) {
		include("header.php");
		OpenTable();
		echo "<center><font class=\"title\"><b>" . ($stop ? _LOGININCOR : _USERREGLOGIN) . "</b></font></center>";
		CloseTable();
		echo "<br>";
		if (!is_user($user)) {
			OpenTable();
			loginbox();
			CloseTable();
		}
		include("footer.php");
	} elseif (is_user($user)) {
		global $cookie;
		cookiedecode($user);
		userinfo($cookie[1]);
	}
}

switch($op) {
	default:
		main($user);
	break;
}

?>
