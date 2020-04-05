<?php 
include("config.php"); 
mysql_connect("$dbhost", "$dbuname", "$dbpass"); 
mysql_select_db("$dbname"); 
echo mysql_error(); 
phpinfo(); 
?>