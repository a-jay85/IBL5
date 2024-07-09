<?
include "includes/classes.inc.php";

$statement = "select team.team_name, team.team_id
from team where team.team_chat_time > '" . date("Y-m-d H:i:s", strtotime("-10 seconds")) . "'
order by team_name";
$result = mysql_query($statement);
echo mysql_error();
$users = array();
while ($row = mysql_fetch_array($result)) {
    if ($row['team_id'] != $login->team_id()) {
        $users[] = '<a href="javascript:private_chat(\'' . $row['team_id'] . '\')">' . $row['team_name'] . '</a>';
    } else {
        $users[] = $row['team_name'];
    }
}
echo '<p style="font-size: 13px; line-height: 15px; font-weight: bold">' . implode("<br>", $users);
