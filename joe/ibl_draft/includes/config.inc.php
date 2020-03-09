<?
$user = "iblhoops";
$password = "Underthedome19!";
$database = "iblhoops_v5draft";
$host = "localhost";
mysql_connect($host, $user, $password) or die ("I cannot connect to the database server because: ".mysql_error());
mysql_select_db($database) or die ("I cannot connect to the database because: ".mysql_error());
define (kAdminUser, '33');
?>