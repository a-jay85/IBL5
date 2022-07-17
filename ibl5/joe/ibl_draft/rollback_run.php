<?
// Rolls back to the selected pick
include "includes/classes.inc.php";

// Admin only!
if ($login->is_admin() && $_POST['pick_id']) {
    // First get a list of the newly available players
    $tables[] = "pick";
    $wheres[] = "pick.pick_id >= '" . $_POST['pick_id'] . "'";
    $tables[] = "player";
    $wheres[] = "player.player_id = pick.player_id";
    $col[] = "player.player_name";
    $tables[] = "position";
    $wheres[] = "position.position_id = player.position_id";
    $col[] = "position.position_name";

    $statement = "select " . implode(",", $col) . " from " . implode(",", $tables) . " where " . implode(" and ", $wheres) . " order by pick_id";
    $result = mysql_query($statement);
    if (mysql_num_rows($result)) {
        // Compose the e-mail and send to all registered teams
        $subject = $settings->get_value(kSettingLeagueName) . " Draft Rollback Notification";
        $message = "The draft has been rolled back to pick " . calculate_pick($_POST['pick_id']) . ".
The following players are back on the board.  If you had them in your queue you will need to re-add them.";
        while ($row = mysql_fetch_array($result)) {
            $message .= '

' . $row['player_name'] . ' (' . $row['position_name'] . ')';
        }
        // send this message to each team
        $statement = "select * from team where team_id = '" . kAdminUser . "'";
        $row = mysql_fetch_array(mysql_query($statement));
        $from = "FOF Conscriptor Admin <" . $row['team_email'] . ">";
        if ($settings->get_value(kSettingSendMails)) {
            $statement = "select * from team where team_email is not NULL";
            $result = mysql_query($statement);
            while ($row = mysql_fetch_array($result)) {
                mail($row['team_email'], $subject, $message, "From: $from");
            }
        } else {
            // At least send the message to the admin
            mail($from, $subject, $message, "From: $from");
        }
    }
    // Now we can roll back the pick
    $statement = "update pick set player_id = '" . kDraftHalt . "',
pick_expired = NULL
where pick_id >= '" . $_POST['pick_id'] . "'";
    mysql_query($statement);
    $_SESSION['message'] = "Draft rollback completed.";

    // Re-set the team_teen table
    $statement = "update team_need set pick_id = NULL";
    mysql_query($statement);
    fill_team_need();
}

// Return to the main page
header("Location: options.php");
