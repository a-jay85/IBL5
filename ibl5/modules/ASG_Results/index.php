<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2006 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if (!eregi("modules.php", $_SERVER['PHP_SELF'])) {
	die ("You can't access this file directly...");
}

require_once("mainfile.php");
$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$userpage = 1;
include("header.php");

$query2="select count(name) as votes,name from (select East_F1 as name from IBL_ASG_Votes union all select East_F2 from IBL_ASG_Votes union all select East_C from IBL_ASG_Votes) as tbl group by name having count(name) > 0 order by 1 desc;";
$result2=mysql_query($query2);
$num2=mysql_num_rows($result2);

$query3="select count(name) as votes,name from (select East_G1 as name from IBL_ASG_Votes union all select East_G2 from IBL_ASG_Votes) as tbl group by name having count(name) > 0 order by 1 desc;";
$result3=mysql_query($query3);
$num3=mysql_num_rows($result3);

$query5="select count(name) as votes,name from (select West_F1 as name from IBL_ASG_Votes union all select West_F2 from IBL_ASG_Votes union all select West_C from IBL_ASG_Votes) as tbl group by name having count(name) > 0 order by 1 desc;";
$result5=mysql_query($query5);
$num5=mysql_num_rows($result5);

$query6="select count(name) as votes,name from (select West_G1 as name from IBL_ASG_Votes union all select West_G2 from IBL_ASG_Votes) as tbl group by name having count(name) > 0 order by 1 desc;";
$result6=mysql_query($query6);
$num6=mysql_num_rows($result6);








OpenTable();
$h=0;
$i=0;
$n=0;
$o=0;

while ($h < $num2)

{

	$player[$h]=mysql_result($result2,$h, "name");
	$votes[$h]=mysql_result($result2,$h);

	$table_echo1=$table_echo1."<tr><td>".$player[$h]."</td><td>".$votes[$h]."</td></tr>";

	$h++;
}
$text1=$text1."<table class=\"sortable\" border=1>
		  <tr><th>Player</th><th> Votes</th></tr>$table_echo1</table><br><br>";

while ($i < $num3)

{

	$player[$i]=mysql_result($result3,$i, "name");
	$votes[$i]=mysql_result($result3,$i);

	$table_echo2=$table_echo2."<tr><td>".$player[$i]."</td><td>".$votes[$i]."</td></tr>";

	$i++;
}
$text2=$text2."<table class=\"sortable\" border=1>
		  <tr><th>Player</th><th> Votes</th></tr>$table_echo2</table><br><br>";

while ($n < $num5)

{

	$player[$n]=mysql_result($result5,$n, "name");
	$votes[$n]=mysql_result($result5,$n);

	$table_echo4=$table_echo4."<tr><td>".$player[$n]."</td><td>".$votes[$n]."</td></tr>";

	$n++;
}
$text4=$text4."<table class=\"sortable\" border=1>
		  <tr><th>Player</th><th> Votes</th></tr>$table_echo4</table><br><br>";

while ($o < $num6)

{

	$player[$o]=mysql_result($result6,$o, "name");
	$votes[$o]=mysql_result($result6,$o);

	$table_echo5=$table_echo5."<tr><td>".$player[$o]."</td><td>".$votes[$o]."</td></tr>";

	$o++;
}
$text5=$text5."<table class=\"sortable\" border=1>
		  <tr><th>Player</th><th> Votes</th></tr>$table_echo5</table><br><br>";


echo "<b>Eastern Conference Frontcourt</b>";
echo $text1;
echo "<b>Eastern Conference Backcourt</b>";
echo $text2;
echo "<b>Western Conference Frontcourt</b>";
echo $text4;
echo "<b>Western Conference Backcourt</b>";
echo $text5;
CloseTable();

include("footer.php");

?>
