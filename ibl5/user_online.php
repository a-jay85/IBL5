<?
session_start();
$session = session_id();
$time = time();
$time_check = $time - 300; //SET TIME 5 Minute

require 'mainfile.php';

$tbl_name = "user_online"; // Table name

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

// if over 5 minute, delete session
$sql4 = "DELETE FROM $tbl_name WHERE time<$time_check";
$result4 = $db->sql_query($sql4);

$db->sql_close();

// Open multiple browser page for result
