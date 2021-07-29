<?
/***************************************************************************
 *                                functions.inc.php
 *                            -------------------
 *   begin                : Friday, Mar 28, 2008
 *   copyright            : (C) 2008 J. David Baker
 *   email                : me@jdavidbaker.com
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

function make_pick($pick_id, $player_id, $recursive = false) {
  global $settings;
  require_once $_SERVER['DOCUMENT_ROOT'] . '/discordWebhooks.php';
  if ($pick_id) {
    $statement = "select * from pick where player_id = '$player_id'";
    // Make sure that the player has not already been picked
    if (!mysql_num_rows(mysql_query($statement))) {
      $statement = "update pick set player_id = '$player_id'
where pick_id = '$pick_id' and (player_id is NULL or player_id = '".kSkipPick."')";
      mysql_query($statement);

      // Send e-mail only if we actually modified a row
      if (mysql_affected_rows()) {
	$statement = "select * from team where team_id = '".kAdminUser."'";
	$row = mysql_fetch_array(mysql_query($statement));
	$from = "IBL Draft Admin <".$row['team_email'].">";
	$statement = "select team.team_name, player.player_name, position.position_name from
pick, team, player, position where
pick.pick_id = '$pick_id' and
team.team_id = pick.team_id and
player.player_id = pick.player_id and
position.position_id = player.position_id";
	$row = mysql_fetch_array(mysql_query($statement));
	$round = floor(($pick_id-1)/28)+1;
	$pick = (($pick_id-1)%28)+1;
	$subject = $settings->get_value(kSettingLeagueName)." Draft Selection Notification: Round $round Pick $pick";
    $queryCurrentSeasonEndingYear = mysql_query('SELECT value
        FROM iblhoops_ibl5.`nuke_ibl_settings`
        WHERE name = "Current Season Ending Year"
        LIMIT 1');
    $currentSeasonEndingYear = mysql_result($queryCurrentSeasonEndingYear, 0);
	$message = "With pick $pick_id of the $currentSeasonEndingYear IBL Draft, **" . $row['team_name'] . "** selects **" . $row['position_name'] . " " . $row['player_name'] . "!**";
	// Who is on the clock?
	$statement = "select pick.pick_id, team.team_id, team_email, team_name, team_email_prefs, team_discord_id
from pick, team
where pick.team_id = team.team_id
and pick.player_id is NULL
order by pick_id
limit 1";
	$row = mysql_fetch_array(mysql_query($statement));
	if ($row['team_name']) {
	  $on_clock = $row['team_id'];
	  $message .= '

**<@!' . $row['team_discord_id'] . '>** is on the clock!';
      // Post pick details to Discord
      postToDiscordChannel('#draft-picks', $message);
	  // Store the e-mail of the team that's on the clock for the notice below
	  if ($row['team_email_prefs'] == kOptionNoEmail) {
	    $clock_email = '';
	  } else {
	    $clock_email = $row['team_email'];
	  }
	  // Mark that they are on the clock
	  $team = new team($row['team_id']);
	  $new_start = $team->new_pick_time(time());
	  $statement = "update pick set pick.pick_time = '$new_start', pick_start = '".date("Y-m-d H:i:s")."'
where pick_id = '".$row['pick_id']."'";
	  mysql_query($statement);
	} else {
	  // We are either done or halted
	  $statement = "select count(*) num from pick where player_id = '".kDraftHalt."'";
	  $row = mysql_fetch_array(mysql_query($statement));
	  if ($row['num']) {
	    // Draft is halted
	    $message .= '

Draft is stopped.';
	  } else {
	    $message .= '

Draft is complete!';
      // Post pick details to Discord
      postToDiscordChannel('#draft-picks', $message);
	  }
	}
	// Also send notifications to those who wish to receive them
	switch($settings->get_value(kSettingSendMails)) {
	case kEmailAll:
	  $statement = "select * from team where team_email is not NULL and
team_id != '".kAdminUser."' and team_email_prefs != ".kOptionNoEmail;
	  $result = mysql_query($statement);
	  $to = array();
	  while ($row = mysql_fetch_array($result)) {
	    // if the team only wants e-mails when they are going on the clock, then only send
	    // if they are on the clock.
	    if (($row['team_email_prefs'] == kOptionMyEmail && $row['team_id'] == $on_clock) ||
		$row['team_email_prefs'] == kOptionAllEmail) {
	      $to[] = $row['team_email'];
	    }
	  }
	  if (defined('kNotificationEmail')) {
	    $to[] = kNotificationEmail;
	  }
	  break;
	case kEmailNextPick:
	  // Only send to the team that's on the clock if that is what is set
	  if ($clock_email) {
	    $to[] = $clock_email;
	  } else {
	    // No clock_email
	    $to = array();
	  }
	  break;
	case kEmailOff:
	default:
	  // Don't send any BCC's if the mail setting is off
	  $to = array();
	}
	mail ($from, $subject, $message, "From: $from\nBCC: ".implode(",",$to));
      } else {
	// The player needs to be removed from the queues
	// This can happen if the e-mails time out the selections
	$statement = "delete from selection where player_id = '$player_id'";
	mysql_query($statement);
      }

      // Delete the player from anyone's queue
      // Re-grab the player to make sure we don't have a race condition
      $statement = "select * from pick where pick_id = '$pick_id'";
      $row = mysql_fetch_array(mysql_query($statement));
      echo mysql_error();
      $player_id = $row['player_id'];
      $statement = "delete from selection where player_id = '$player_id'";
      mysql_query($statement);

      // Find the team that made this pick and process their queue accordingly
      $statement = "select team.team_id, team.team_multipos, player.position_id from
pick, team, player
where
pick.pick_id = '$pick_id' and
team.team_id = pick.team_id and
player.player_id = pick.player_id";
      $row = mysql_fetch_array(mysql_query($statement));
      $team_id = $row['team_id'];
      if ($row['team_multipos'] == '0') {
	// Clear out any other players from this team's queue that have the same position
	$statement = "select player.player_id from player, selection where
player.position_id = '".$row['position_id']."' and
player.player_id = selection.player_id and
selection.team_id = '".$row['team_id']."'";
	$result = mysql_query($statement);
	while($row = mysql_fetch_array($result)) {
	  $statement = "update selection set selection_priority = '0' where
team_id = '".$team_id."' and player_id = '".$row['player_id']."'";
	  mysql_query($statement);
	}
      }
      // Mark the team_need for this team at this position as being done
      $statement = "select position_id from player where player_id = '$player_id'";
      $row = mysql_fetch_array(mysql_query($statement));
      $statement = "update team_need set pick_id = '$pick_id' where team_id = '$team_id' and
position_id = '".$row['position_id']."'";
      mysql_query($statement);
    }

    // Traverse the pick queue
    if ($recursive) {
      process_pick_queue();
    }
  }
}

function process_pick_queue() {
  // Lock the database
  //$statement = "flush tables with read lock";
  //mysql_query($statement);
  // First check for any skipped team's picks
  $statement = "select pick.pick_id, team.team_id, pick.pick_time from pick, team where
pick.player_id = '".kSkipPick."'
and pick.team_id = team.team_id
order by pick.pick_id";
  $result = mysql_query($statement);
  while ($row = mysql_fetch_array($result)) {
    if ($row['team_id']) {
      $team = new team($row['team_id']);
      $player_id = $team->next_player($row['pick_start']);
      if ($player_id) {
	make_pick($row['pick_id'], $player_id);
	// make_pick will recursively call this function so we can just bail if we get this far
	return;
      }
    }
  }
  // See if the next team in the queue has a selection and make it if they do.
  $tables[] = "pick";
  $col[] = "pick.pick_id";
  $col[] = "pick.pick_time";
  $col[] = "pick.pick_start";
  $tables[] = "team";
  $col[] = "team.team_id";
  $wheres[] = "team.team_id = pick.team_id";
  $wheres[] = "pick.player_id is NULL";

  $statement = "select ".implode(",",$col)." from (".implode(",",$tables).")
where ".implode(" and ",$wheres)."
order by pick.pick_id limit 1";

  $row = mysql_fetch_array(mysql_query($statement));
  $team = new team($row['team_id']);
  $player_id = $team->next_player($row['pick_start']);
  if ($player_id) {
    make_pick($row['pick_id'], $player_id);
  }
}

function height_convert($inches) {
  if ($inches) {
    // Returns $inches in the feet and inches
    $feet = floor($inches/12);
    $inches = $inches%12;
    return "$feet' $inches\"";
  }
}

function calculate_pick($pick) {
  return (floor(($pick-1)/28)+1).' - '.((($pick-1)%28)+1).' ('.$pick.')';
}

function process_expired_picks() {
  global $settings;
  require_once $_SERVER['DOCUMENT_ROOT'] . '/discordWebhooks.php';
  $limit = $settings->get_value(kSettingPickTimeLimit);
  if (!$limit) {
    // No limit, nothing to do
    return;
  }
  $tables[] = "pick";
  $col[] = "pick.pick_id";
  $col[] = "team.team_id";
  $tables[] = "team";
  $wheres[] = "team.team_id = pick.team_id";
  $wheres[] = "pick.player_id is NULL";
  $col[] = "pick.pick_time";
  $col[] = "if(team.team_clock_adj != '0',
time_to_sec(timediff(date_add(pick.pick_time, interval (".$limit."*team.team_clock_adj) minute), '".date("Y-m-d H:i:s")."')),
-1) time_left";
  $col[] = "team.team_clock_adj";
  $statement = "select ".implode(",",$col)." from ".implode(",",$tables)." where ".implode(" and ",$wheres)."
order by pick_id
limit 1";
  $row = mysql_fetch_array(mysql_query($statement));
  if ($row['time_left'] < 0) {
    // Adjust the team's autopick value
    $team = new team($row['team_id']);
    $team->lower_pick_limit();
    // Pick has expired!
    if ($discordPosted !== TRUE) {
        $statement = "select pick.pick_id, team.team_id, team_email, team_name, team_email_prefs, team_discord_id
    from pick, team
    where pick.team_id = team.team_id
    and pick.player_id is NULL
    order by pick_id
    limit 2";
        $result = mysql_query($statement);
        $skippedTeam = mysql_fetch_array($result);
        // $message .= $row[team_name];
        $message .= '<@!' . $skippedTeam['team_discord_id'] . '>\'s pick **has been skipped** – their pick clock **expired**.

*They are still allowed to make their pick when they choose to.*

';
        $teamOnTheClock = mysql_fetch_array($result);
        $on_clock = $teamOnTheClock['team_id'];
        $message .= '**<@!' . $teamOnTheClock['team_discord_id'] . '>** is now on the clock!';

        // Post skip details to Discord
        if (postToDiscordChannel('#draft-picks', $message)) {
            $discordPosted = TRUE;
        };
    }
    $old_start = $row['pick_time'];
    $limit = $limit * $row['team_clock_adj'];
    if ($settings->get_value(kSettingExpiredPick) == kExpireMakePick) {
      $player_id = $team->force_pick();
      // Mark that this pick has expired
      $statement = "update pick set pick_expired = '1' where pick_id = '".$row['pick_id']."'";
      mysql_query($statement);
      if ($player_id) {
	make_pick($row['pick_id'], $player_id, false);
      } else {
	$statement = "update pick set player_id = '".kSkipPick."' where pick_id = '".$row['pick_id']."'";
	mysql_query($statement);
	$player_id = true;
      }
    } else {
      $statement = "update pick set player_id = '".kSkipPick."' where pick_id = '".$row['pick_id']."'";
      mysql_query($statement);
      $player_id = true;
    }
    if ($player_id) {
      // Next pick starts where this one expired
      $next_pick = $row['pick_id']+1;
      $statement = "select * from pick where pick_id = '$next_pick'";
      $row = mysql_fetch_array(mysql_query($statement));
      $team = new team($row['team_id']);
      $new_start = $team->new_pick_time(strtotime("+".ceil($limit)." minutes", strtotime($old_start)));
      $statement = "update pick set pick_time = '$new_start', pick_start = min('$new_start', '".date("Y-m-d H:i:s")."')
where pick_id = '$next_pick'";
      mysql_query($statement);
      // Re-process the pick queue
      process_pick_queue();
      // Recursively call to see if this next pick is also expired
      process_expired_picks();
    }
  }
}

function reset_current_pick_clock() {
  // Checks the current pick and sets the clock if the clock is not yet set
  $statement = "select * from pick where player_id is NULL order by pick_id limit 1";
  $row = mysql_fetch_array(mysql_query($statement));
  if ($row['pick_id'] && !$row['pick_time']) {
    $team = new team($row['team_id']);
    $statement = "update pick set pick_time = '".$team->new_pick_time(time())."',
pick_start = '".date("Y-m-d H:i:s")."'
where pick_id = '".$row['pick_id']."'";
    mysql_query($statement);
  }
}

function fill_team_need() {
  $statement = "select pick_id, position_id, team_id from pick, player where
player.player_id = pick.player_id";
  $result = mysql_query($statement);
  while ($row = mysql_fetch_array($result)) {
    $team_id = $row['team_id'];
    $pick_id = $row['pick_id'];
    $position_id = $row['position_id'];
    $statement = "update team_need set pick_id = '$pick_id' where team_id = '$team_id' and position_id = '$position_id'";
    mysql_query($statement);
  }
}

// This last bit of code converts plaintext passwords in the database to encrypted
function encrypt_passwords() {
  $statement = "select * from team where team_password is not NULL and char_length(team_password) < 32";
  $result = mysql_query($statement);
  echo mysql_error();
  while ($row = mysql_fetch_array($result)) {
    $statement = "update team set team_password = '".md5($row['team_password'])."' where team_id = '".$row['team_id']."'";
    mysql_query($statement);
  }
}
?>
