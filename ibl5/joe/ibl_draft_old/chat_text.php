<?
include "includes/classes.inc.php";

if (!$_SESSION['chat_time']) {
  $_SESSION['chat_time'] = date("Y-m-d H:i:s");
 }

$chat_id = $_GET['chat_id'];
$chat_room_id = $_GET['chat_room_id'];
if ($chat_room_id) {
  // Make sure we have access to this room
  $statement = "select * from chat_room where chat_room_id = '".$_GET['chat_room_id']."'";
  $row = mysql_fetch_array(mysql_query($statement));
  if ($row['team_1_id'] != $login->team_id() && $row['team_2_id'] != $login->team_id()) {
    exit;
  }
  if (!$row['team_2_arrived']) {
    if ($_SESSION['waiting_time'][$chat_room_id] < time()) {
      $html .= '
<span class="chat_text"><b>Waiting...</b></span><br>';
      $_SESSION['waiting_time'][$chat_room_id] = strtotime("+10 seconds");
      $statement = "update chat_room set chat_room_ping = '".date("Y-m-d H:i:s")."' where
chat_room_id = '$chat_room_id'";
      mysql_query($statement);
      echo $html;
    }
  }
 }
$last_chat = $_SESSION['last_printed_chat'][$chat_id];
$statement = "select * from chat, team where team.team_id = chat.team_id and chat_id > '".$last_chat."'";
if ($chat_room_id) {
  $statement .= " and chat_room_id = '$chat_room_id'";
 } else {
  $statement .= " and chat_room_id is NULL";
 }
// Allow the admin to see the full chat transcript
if (!$login->is_admin()) { $statement .= " and chat_time > '".$_SESSION['chat_time']."'"; }
$statement .= "
order by chat_time";
$result = mysql_query($statement);
while($row = mysql_fetch_array($result)) {
  if ($login->is_admin() || 1) {
    $html .= '
<span class="chat_time">('.date("m/d g:i:s a T", strtotime($row['chat_time'])).')</span><br>';
  }
  $html .= '
<span class="chat_text"><b>'.$row['team_name'].':</b> '.htmlentities($row['chat_message']).'<br></span>';
  $_SESSION['last_chat_id'] = $row['chat_id'];
  $_SESSION['last_printed_chat'][$chat_id] = $row['chat_id'];
 }
echo $html;
?>