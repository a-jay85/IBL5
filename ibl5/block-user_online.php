<?
session_start();
$session = session_id();
$time = time();
$time_check = $time - 600; //SET TIME 10 Minute

$host = "localhost"; // Host name
$username = "iblhoops"; // Mysql username
$password = "Oliver!1"; // Mysql password
$db_name = "iblhoops_ibl5"; // Database name
$tbl_name = "user_online"; // Table name

// Connect to server and select databse
mysql_connect("$host", "$username", "$password") or die("cannot connect to server");
mysql_select_db("$db_name") or die("cannot select DB");

$sql = "SELECT * FROM $tbl_name WHERE session='$session'";
$result = $db->sql_query($sql);

$count = $db->sql_numrows($result);

if ($count == "0") {
    $sql1 = "INSERT INTO $tbl_name(session, time)VALUES('$session', '$time')";
    $result1 = $db->sql_query($sql1);
} else {
    "$sql2=UPDATE $tbl_name SET time='$time' WHERE session = '$session'";
    $result2 = $db->sql_query($sql2);
}

$sql3 = "SELECT * FROM $tbl_name";
$result3 = $db->sql_query($sql3);

$count_user_online = $db->sql_numrows($result3);

echo "User online : $count_user_online ";

// if over 10 minute, delete session
$sql4 = "DELETE FROM $tbl_name WHERE time<$time_check";
$result4 = $db->sql_query($sql4);

$db->sql_close();

// Open multiple browser page for result
