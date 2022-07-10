<?php 
include("config.php"); 
mysql_connect("$dbhost", "$dbuname", "$dbpass"); 
mysql_select_db("$dbname"); 
echo $db->sql_error(); 
phpinfo(); 
?>