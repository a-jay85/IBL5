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

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
	die ("You can't access this file directly...");
}

require_once("mainfile.php");
$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$userpage = 1;
include("header.php");

$query1="select sum(score) as votes,name from (select MVP_1 as name, 3 as score from IBL_EOY_Votes union all select MVP_2 as name, 2 as score from IBL_EOY_Votes union all select MVP_3 as name, 1 as score from IBL_EOY_Votes) as tbl group by name;";
$result1=mysql_query($query1);
$num1=mysql_num_rows($result1);

$query15="select sum(score) as votes,name from (select MVP_1 as name, 1 as score from IBL_EOY_Votes) as tbl group by name;";
$result15=mysql_query($query15);
$num15=mysql_num_rows($result15);


$query2="select sum(score) as votes,name from (select Six_1 as name, 3 as score from IBL_EOY_Votes union all select Six_2 as name, 2 as score from IBL_EOY_Votes union all select Six_3 as name, 1 as score from IBL_EOY_Votes) as tbl group by name;";
$result2=mysql_query($query2);
$num2=mysql_num_rows($result2);

$query3="select sum(score) as votes,name from (select ROY_1 as name, 3 as score from IBL_EOY_Votes union all select ROY_2 as name, 2 as score from IBL_EOY_Votes union all select ROY_3 as name, 1 as score from IBL_EOY_Votes) as tbl group by name;";
$result3=mysql_query($query3);
$num3=mysql_num_rows($result3);

$query4="select sum(score) as votes,name from (select GM_1 as name, 3 as score from IBL_EOY_Votes union all select GM_2 as name, 2 as score from IBL_EOY_Votes union all select GM_3 as name, 1 as score from IBL_EOY_Votes) as tbl group by name;";
$result4=mysql_query($query4);
$num4=mysql_num_rows($result4);









OpenTable();
$k=0;
$h=0;
$i=0;
$m=0;

while ($k < $num1)

{

	$player[$k]=mysql_result($result1,$k, "name");
	$votes[$k]=mysql_result($result1,$k);


	$table_echo=$table_echo."<tr><td>".$player[$k]."</td><td>".$votes[$k]."</td></tr>";

	$k++;
}



$text=$text."<table class=\"sortable\" border=1>
		  <tr><th>Player</th><th> Score</th></tr>$table_echo</table><br><br>";




while ($h < $num2)

{

	$player[$h]=mysql_result($result2,$h, "name");
	$votes[$h]=mysql_result($result2,$h);

	$table_echo1=$table_echo1."<tr><td>".$player[$h]."</td><td>".$votes[$h]."</td></tr>";

	$h++;
}
$text1=$text1."<table class=\"sortable\" border=1>
		  <tr><th>Player</th><th> Score</th></tr>$table_echo1</table><br><br>";

while ($i < $num3)

{

	$player[$i]=mysql_result($result3,$i, "name");
	$votes[$i]=mysql_result($result3,$i);

	$table_echo2=$table_echo2."<tr><td>".$player[$i]."</td><td>".$votes[$i]."</td></tr>";

	$i++;
}
$text2=$text2."<table class=\"sortable\" border=1>
		  <tr><th>Player</th><th> Score</th></tr>$table_echo2</table><br><br>";

while ($m < $num4)

{

	$player[$m]=mysql_result($result4,$m, "name");
	$votes[$m]=mysql_result($result4,$m);

	$table_echo3=$table_echo3."<tr><td>".$player[$m]."</td><td>".$votes[$m]."</td></tr>";

	$m++;
}
$text3=$text3."<table class=\"sortable\" border=1>
		  <tr><th>Player</th><th> Score</th></tr>$table_echo3</table><br><br>";




echo "<b>Most Valuable Player</b>";
echo $text;
echo "<b>Sixth Man of the Year</b>";
echo $text1;
echo "<b>Rookie of the Year</b>";
echo $text2;
echo "<b>GM of the Year</b>";
echo $text3;

CloseTable();

include("footer.php");

?>
