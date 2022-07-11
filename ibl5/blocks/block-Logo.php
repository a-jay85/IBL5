<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2005 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if ( !defined('BLOCK_FILE') ) {
    Header("Location: ../index.php");
    die();
}

global $db;

$actual_url = "$_SERVER[REQUEST_URI]";

$boxstuff = "<span class=\"content\">";

if (
    $actual_url == "/" ||
    $actual_url == "/index.php" ||
    $actual_url == "/ibl5/" ||
    $actual_url == "/ibl5/index.php"
) {} else {
	$boxstuff .= '<a href="index.php"><img src="logocorner.jpg" border="0"></a>';
}

$boxstuff .= "</span>";

$content = $boxstuff;

?>
