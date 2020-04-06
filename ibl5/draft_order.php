<?php

$username = "iblhoops_joe";
$password = "celtics";
$database = "iblhoops_v4draft";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");


$querya="load data local infile 'http://www.iblhoops.net/ibl5/spreadsheets/draft_order.csv' into table excel FIELDS TERMINATED BY ',' ENCLOSED BY '\"' ESCAPED BY '\\' (pick, team, tid)";
$resulta=mysql_query($querya);

$queryb="update excel a, team b set a.tid = b.team_id where a.team = b.full_name";
$resultb=mysql_query($queryb);


$queryc="update pick a, excel b set a.team_id = b.tid where a.pick_id = b.pick";
$resultc=mysql_query($queryc);

echo "Draft-o-Matic setup is complete. Do NOT contact Joe. Seriously. Don't do it."


?>
