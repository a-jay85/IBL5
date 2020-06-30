<?php

require 'config.php';
$dbname = "iblhoops_ibldraft";
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");


$querya="load data local infile 'http://www.iblhoops.net/spreadsheets/draft_order.csv' into table excel FIELDS TERMINATED BY ',' ENCLOSED BY '\"' ESCAPED BY '\\' (pick, team, tid)";
$resulta=mysql_query($querya);

$queryb="update excel a, team b set a.tid = b.team_id where a.team = b.full_name";
$resultb=mysql_query($queryb);


$queryc="update pick a, excel b set a.team_id = b.tid where a.pick_id = b.pick";
$resultc=mysql_query($queryc);

echo "Draft-o-Matic setup is complete. Do NOT contact Joe. Seriously. Don't do it."


?>