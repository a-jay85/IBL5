<?
include "includes/classes.inc.php";

if(get_magic_quotes_gpc()) {
  $_POST['message'] = stripslashes($_POST['message']);
}

if ($_POST['chat_room_id']) {
  $statement = "select * from chat_room where team_1_id = '".$login->team_id()."' or team_2_id = '".$login->team_id()."'
and chat_room_id = '".$_POST['chat_room_id']."'";
  if (mysql_num_rows(mysql_query($statement))) {
    $statement = "insert into chat (team_id, chat_time, chat_message, chat_room_id) values
('".$login->team_id()."', '".date("Y-m-d H:i:s")."', '".addslashes($_POST['message'])."', '".$_POST['chat_room_id']."')";
    mysql_query($statement);
  }
 } else {
  $statement = "insert into chat (team_id, chat_time, chat_message) values
('".$login->team_id()."', '".date("Y-m-d H:i:s")."', '".addslashes($_POST['message'])."')";
  mysql_query($statement);
 }
echo $statement;
?>