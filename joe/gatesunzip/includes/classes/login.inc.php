<?
/***************************************************************************
 *                                login.inc.php
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

define(kOptionNoEmail, 0);
define(kOptionAllEmail, 1);
define(kOptionMyEmail, 2);

class login {
  function login() {
    // Check for stored login info
    if ($_COOKIE['fof_draft_login_team_name']) {
      $_SESSION['fof_draft_login_team_name'] = $_COOKIE['fof_draft_login_team_name'];
      $_SESSION['fof_draft_login_team_password'] = $_COOKIE['fof_draft_login_team_password'];
    }
    $statement = "select * from team where team_name like '".mysql_real_escape_string($_SESSION['fof_draft_login_team_name'])."' and
team_password = '".mysql_real_escape_string($_SESSION['fof_draft_login_team_password'])."'";
    $this->data = mysql_fetch_array(mysql_query($statement));
    if (!$this->data['team_id'] && kRedirect) {
      if ($_SESSION['fof_draft_login_team_name']) {
	$_SESSION['message'] = "That login is not valid.";
	unset($_SESSION['fof_draft_login_team_name']);
	unset($_SESSION['fof_draft_login_team_password']);
	setcookie("fof_draft_login_team_name", "", time() - 3600);
	setcookie("fof_draft_login_team_password", "", time() - 3600);
	header("Location: selections.php");
	exit;
      }
    }
    // Update the chat time stamp for this user
    $statement = "update team set team_chat_time = '".date("Y-m-d H:i:s")."' where team_id = '".$this->team_id()."'";
    mysql_query($statement);
  }

  function team_id() {
    return $this->data['team_id'];
  }

  function team_name() {
    return $this->data['team_name'];
  }

  function is_admin() {
    if ($this->data['team_id'] == kAdminUser) {
      return true;
    } else {
      return false;
    }
  }

  function pick_method() {
    return $this->data['pick_method_id'];
  }

  function draw_options() {
    global $settings;
    $html .= '
<h3>Options</h3>';
    if ($this->is_admin()) {
      $html .= '
<p>Set the options for the admin account.  Enter an e-mail address to receive notification of each pick.
This address will also be the address that the notifications are sent from, so you must have an address here.</p>
<p>Some web hosts do not queue their outgoing mail in such a way that a script can send several e-mails
without timing out.  If you are experiencing long wait times after a pick has been made and/or errors
when making picks, you might try turning e-mail notification off.  It is turned off by default.
The admin e-mail address will still receive notification e-mails.</p>
<p>The league name will be in the subject of all outgoing e-mails regarding the draft as well as in the
window title.</p>
<p>The maximum delay for autopick is the longest time a team can delay their pick without turning
their autopick off.  This can be up to the pick time limit or 30 minutes if no pick time limit is set.</p>
<p>The pick time limit will cause the current pick to be skipped or the BPA selected at the end of the time limit,
and the clock in the menu will count down rather than up.  Set the limit to 0:00 to turn this option off.  You can
also set the time of day that the pick time limit is active for.  This setting only affects the pick time limit,
teams will still be able to make selections outside of this time rage unless the draft is stopped.  The draft
must be stopped in order to change these values.</p>
<p>If the setting for "When a team\'s time expires" is set to "Choose BPA/Scout Pick," the system will first see if that team
has a priority list and just has autopick turned off, and will choose from that list.  If there is not a player
with that method, if a mock draft has been performed, it will use the team\'s roster data to do a scout pick.  If
no mock draft has been performed, it will choose the best player
available based on the adjusted grade or, if the adjusted grade is not uploaded, the order of players as uploaded,
without selecting a position that has already been selected for that team.</p>
<p>To stop the draft at a certain pick, use the "Stop draft at"
option.  When a pick is selected in the "Stop draft at" option, the selected pick will not run.<br>
Stopping and restarting the draft will reset the clock for the current pick.</p>';
    } else {
      $html .= '
<p>Set the options for your account.  Enter your e-mail address if you would like to have each pick
e-mailed to you as the selections are made, leave it blank to not receive an e-mail.';
      if (!$settings->get_value(kSettingSendMails)) {
	$html .= ' <b>NOTE:</b> The admin has disabled the e-mail notification.';
      }
      $html .= '
<p>If you turn "Auto pick" off, the system will require you to manually make your draft picks.
When you are on the clock, the list under the "Priority" tab will have a "Select Player" link
to choose that player.  If "Auto pick" is on (the default), the system will select the highest-priority
player in your list when you are on the clock..</p>
<p>By default, when you select a player, all other players of the same position in your queue have their
priority reset to zero.  This will prevent you from accidentally selecting two players of the same position
unintentionally.  If you want to turn this feature off, uncheck the "Zero out priority of players with same
position when selecting a player" option.';
    }
    $html .= '
<form method="post" action="options_set.php">
  <table class="data">
    <tr>
      <td align="right" class="light" width="40%">E-mail:</td>
      <td class="light"><input type="text" name="team_email" value="'.$this->data['team_email'].'"></td>
    </tr>';
    if (!$this->is_admin()) {
      switch($this->data['team_email_prefs']) {
      case kOptionNoEmail:
	$no_email = ' selected';
	break;
      case kOptionAllEmail:
	$all_email = ' selected';
	break;
      case kOptionMyEmail:
	$me_email = ' selected';
	break;
      }
      $html .= '
    <tr>
      <td align="right" class="light" width="40%">E-mail options:</td>
      <td class="light">
        <select name="team_email_prefs">
          <option value="'.kOptionNoEmail.'"'.$no_email.'>No E-mail</option>
          <option value="'.kOptionAllEmail.'"'.$all_email.'>All Picks</option>
          <option value="'.kOptionMyEmail.'"'.$me_email.'>My Picks Only</option>
        </select>
      </td>
    </tr>';
    }
    if ($this->is_admin()) {
      // First check to see if the draft is stopped
      $statement = "select * from pick where player_id is NULL";
      if(mysql_num_rows(mysql_query($statement))) {
	$stopped = false;
	$time_access = " disabled";
	$time_access_value = "0";
      } else {
	$stopped = true;
	$time_access = "";
	$time_access_value = "1";
      }
      $html .= '
    <tr>
      <td align="right" class="light">League Name:</td>
      <td class="light"><input type="text" name="league_name" value="'.$settings->get_value(kSettingLeagueName).'"></td>
    </tr>
    <tr>
      <td align="right" class="light">Send E-mails:</td>
      <td class="light">
        <select name="send_emails">';
      switch ($settings->get_value(kSettingSendMails)) {
      case kEmailAll:
	$off = "";
	$all = " selected";
	$next = "";
	break;
      case kEmailNextPick:
	$off = "";
	$all = "";
	$next = " selected";
	break;
      case kEmailOff:
      default:
	$off = " selected";
	$all = "";
	$next = "";
	break;
      }
      $html .= '
          <option value="'.kEmailAll.'"'.$all.'>Send all picks to all teams</option>
          <option value="'.kEmailNextPick.'"'.$next.'>Only send picks to the team on the clock</option>
          <option value="'.kEmailOff.'"'.$off.'>Only send mail to admin</option>
        </select>
      </td>
    </tr>
    <tr>
      <td align="right" class="light">Maximum delay for autopick:</td>
      <td class="light">
        <select name="max_autopick_delay">
          <option value="">Pick time limit/30 min</option>';
      $time = 5;
      if ($settings->get_value(kSettingPickTimeLimit)) {
	$max = $settings->get_value(kSettingPickTimeLimit);
      } else {
	$max = 30;
      }
      while ($time <= $max) {
	if ($time == $settings->get_value(kSettingMaxDelay)) {
	  $selected = " selected";
	} else {
	  $selected = "";
	}
	$html .= '
          <option value="'.$time.'"'.$selected.'>'.$time.'</option>';
	$time += 5;
      }
      $html .= '
      </td>
    </tr>
    <tr>
      <td align="right" class="light">Pick Time Limit (0:00 for no limit):</td>
      <td class="light">
        <input type="hidden" name="time_access" value="'.$time_access_value.'">
        <select name="pick_limit_hour"'.$time_access.'>';
      $limit = $settings->get_value(kSettingPickTimeLimit);
      $hour = floor($limit/60);
      $min = $limit % 60;
      $h = 0;
      while ($h <= 24) {
	if ($h == $hour)  {
	  $selected = " selected";
	} else {
	  $selected = "";
	}
	$html .= '
          <option value="'.$h.'"'.$selected.'>'.$h.'</option>';
	$h++;
      }
      $html .= '
        </select>
        :
        <select name="pick_limit_min"'.$time_access.'>';
      $m = 0;
      while($m < 60) {
	if ($m == $min) {
	  $selected = " selected";
	} else {
	  $selected = "";
	}
	$html .= '
          <option value="'.$m.'"'.$selected.'>'.sprintf("%02d", $m).'</option>';
	$m += 5;
      }
      $html .= '
        </select>';
      if (!$stopped) {
	$html .= '
(Stop the draft to change)';
      }
      $html .= '
      </td>
    </tr>
    <tr>
      <td align="right" class="light">Daily Start Time (0:00 for 24-hour clock):</td>
      <td class="light">
        <select name="start_hour"'.$time_access.'>';
      $time = $settings->get_value(kSettingStartTime);
      if ($time) {
	$hour = date("g", $time);
	$min = date("i", $time);
	$ampm = date("A", $time);
      } else {
	$hour = 0;
	$min = 0;
	$ampm = '';
      }
      $h = 0;
      while ($h <= 12) {
	if ($h == $hour)  {
	  $selected = " selected";
	} else {
	  $selected = "";
	}
	$html .= '
          <option value="'.$h.'"'.$selected.'>'.$h.'</option>';
	$h++;
      }
      $html .= '
        </select>
        :
        <select name="start_min"'.$time_access.'>';
      $m = 0;
      while($m < 60) {
	if ($m == $min) {
	  $selected = " selected";
	} else {
	  $selected = "";
	}
	$html .= '
          <option value="'.$m.'"'.$selected.'>'.sprintf("%02d", $m).'</option>';
	$m += 5;
      }
      $html .= '
        </select>
        <select name="start_ampm"'.$time_access.'>';
      if ($ampm == 'PM') {
	$pm = ' selected';
	$am = '';
      } else {
	$pm = '';
	$am = ' selected';
      }
      $html .= '
          <option value="AM"'.$am.'>AM</option>
          <option value="PM"'.$pm.'>PM</option>
        </select>
      </td>
    </tr>
    <tr>
      <td align="right" class="light">Daily End Time (0:00 for 24-hour clock):</td>
      <td class="light">
        <select name="end_hour"'.$time_access.'>';
      $time = $settings->get_value(kSettingEndTime);
      if ($time) {
	$hour = date("g", $time);
	$min = date("i", $time);
	$ampm = date("A", $time);
      } else {
	$hour = 0;
	$min = 0;
	$ampm = '';
      }
      $h = 0;
      while ($h <= 12) {
	if ($h == $hour)  {
	  $selected = " selected";
	} else {
	  $selected = "";
	}
	$html .= '
          <option value="'.$h.'"'.$selected.'>'.$h.'</option>';
	$h++;
      }
      $html .= '
        </select>
        :
        <select name="end_min"'.$time_access.'>';
      $m = 0;
      while($m < 60) {
	if ($m == $min) {
	  $selected = " selected";
	} else {
	  $selected = "";
	}
	$html .= '
          <option value="'.$m.'"'.$selected.'>'.sprintf("%02d", $m).'</option>';
	$m += 5;
      }
      $html .= '
        </select>
        <select name="end_ampm"'.$time_access.'>';
      if ($ampm == 'PM') {
	$pm = ' selected';
	$am = '';
      } else {
	$pm = '';
	$am = ' selected';
      }
      $html .= '
          <option value="AM"'.$am.'>AM</option>
          <option value="PM"'.$pm.'>PM</option>
        </select>
        Time zone:
        <select name="time_zone"'.$time_access.'>';
      $time_zone = $settings->get_value(kSettingTimeZone);
      $html .= '
          <option value="">Use Server\'s Time Zone</option>';
      $statement = "select * from time_zone where time_zone_title is not null
order by time_zone_id";
      $result = mysql_query($statement);
      while($row = mysql_fetch_array($result)) {
	if ($row['time_zone_id'] == $time_zone) {
	  $selected = " selected";
	} else {
	  $selected = "";
	}
	$html .= '
          <option value="'.$row['time_zone_id'].'"'.$selected.'>'.$row['time_zone_title'].'</option>';
      }
      $html .= '
        </select>
      </td>
    </tr>
    <tr>
      <td align="right" class="light">If a team goes on the clock with less than the time limit for the day:</td>
      <td class="light">
        <select name="rollover_method"'.$time_access.'>';
      if ($settings->get_value(kSettingRolloverMethod) == kFinishToday) {
	$today = " selected";
      } else {
	$today = "";
      }
      $html .= '
          <option value="'.kRollIntoTomorrow.'">Add the time that the clock is off to the limit</option>
          <option value="'.kFinishToday.'"'.$today.'>Keep the limit for that pick</option>
        </select>
      </td>
    </tr>
    <tr>
      <td align="right" class="light">When a team\'s time expires, reduce their limit percentage by</td>
      <td class="light">
        <select name="autopick_reduction">';
      $autopick_reduction = $settings->get_value(kSettingAutopickReduction);
      $percent = 0;
      while ($percent <= 100) {
	if ($percent == $autopick_reduction) {
	  $selected = " selected";
	} else {
	  $selected = "";
	}
	$html .= '
          <option value="'.$percent.'"'.$selected.'>'.$percent.'%</option>';
	$percent += 5;
      }
      $html .= '
        </select>
      </td>
    </tr>
    <tr>
      <td align="right" class="light">When a team\'s time expires:</td>
      <td class="light">
        <select name="team_expire">';
      if ($settings->get_value(kSettingExpiredPick) == kExpireMakePick) {
	$make_pick = " selected";
      } else {
	$make_pick = "";
      }
      $html .= '
          <option value="'.kExpireSkipPick.'">Skip Pick</option>
          <option value="'.kExpireMakePick.'"'.$make_pick.'>Choose BPA/Scout Pick</option>';
      $html .= '
        </select>
      </td>
    <tr>
      <td align="right" class="light">Stop draft at:</td>
      <td class="light">
        <select name="pick_id">
          <option value="">Run Unimpeded</option>';
      $statement = "select * from pick where player_id is NULL or player_id = '".kDraftHalt."' order by pick_id";
      $result = mysql_query($statement);
      $found = false;
      while ($row = mysql_fetch_array($result)) {
	if (!$found && $row['player_id'] == kDraftHalt) {
	  $selected = " selected";
	  $found = true;
	} else {
	  $selected = "";
	}
	$html .= '
          <option value="'.$row['pick_id'].'"'.$selected.'>'.calculate_pick($row['pick_id']).'</option>';
      }
      $html .= '
        </select>
      </td>
    </tr>';
    } else {
      $html .= '
    <tr>
      <td align="right" class="light">Pick Method:</td>
      <td class="light">
        <select name="pick_method_id">';
      $pick_method = new pick_method($this->data['pick_method_id']);
      $html .= $pick_method->option_list();
      $html .= '
        </select>
      </td>
    </tr>';
      $html .= '
    <tr>
      <td align="right" class="light">Auto pick from selections:</td>
      <td class="light">
        <input type="checkbox" name="team_autopick"';
      if ($this->data['team_autopick'] == '1') {
	$html .= ' checked';
      }
      $html .= '>
      After 
        <select name="team_autopick_wait">';
      $i = 0;
      $limit = $settings->get_value(kSettingMaxDelay);
      if (!$limit) {
	$limit = $settings->get_value(kSettingPickTimeLimit);
      }
      if ($limit) {
	$limit = $limit * $this->data['team_clock_adj'];
      } else {
	$limit = 30;
      }
      while ($i <= $limit) {
	if ($i == $this->data['team_autopick_wait']) {
	  $selected = " selected";
	} else {
	  $selected = "";
	}
	$html .= '
          <option value="'.$i.'"'.$selected.'>'.$i.'</option>';
	$i+=5;
      }
      $html .= '
        </select>
      minutes
    </tr>
    <tr>
      <td align="right" class="light">Zero out priority of players with same position when selecting a player:</td>
      <td class="light">
        <input type="checkbox" name="team_multipos"';
      if ($this->data['team_multipos'] == '0') {
	$html .= ' checked';
      }
      $html .= '>
    </tr>';
    }
    $html .= '
    <tr>
      <td align="right" class="light" valign="top">Show Columns in Player Lists (15 max):
<p>Click on a column name to select/deselect it.</p>';
    if ($this->is_admin()) {
      $html .= '<p><i>(this will only affect your lists,<br>
each team has their own ability to set this for themselves)</i></p>';
    }
    $html .= '</td>
      <td class="light">';
    $html .= '
<div id="column_detail">';
    $html .= $this->draw_column_selections();
    $html .= '
</div>';
    $html .= '
      </td>
    </tr>';
    $html .= '
    <tr>
      <td colspan="2" class="light" align="center">
        <p>Use the following code to embed a widget with the current draft status:</p>
        <textarea cols="80" rows="3" readonly>';
    $widget_location = "http://".$_SERVER['SERVER_NAME'];
    $path = explode("/",$_SERVER['REQUEST_URI']);
    $path[count($path)-1] = "widget.php";
    $widget_location .= implode("/",$path);
    $html .= htmlentities('<center><iframe src="'.$widget_location.'" height="310" width="925"></iframe></center>');
    $html .= '</textarea>
      </td>
    </tr>';
    $html .= '
  </table>
  <p><input type="submit" value="Save">
</form>';
    return $html;
  }

  function select_column() {
    $statement = "select count(*) num from team_to_column where team_id = '".$this->data['team_id']."'";
    $row = mysql_fetch_array(mysql_query($statement));
    if ($row['num'] < 15) {
      if ($_GET['column_id']) {
	$statement = "insert into team_to_column (team_id, column_id)
values
('".$this->data['team_id']."', '".$_GET['column_id']."')";
	mysql_query($statement);
      }
    }
    return $this->draw_column_selections();
  }

  function deselect_column() {
    $statement = "delete from team_to_column where
team_id = '".$this->data['team_id']."' and column_id = '".mysql_real_escape_string($_GET['column_id'])."'";
    mysql_query($statement);
    return $this->draw_column_selections();
  }

  function draw_column_selections() {
    $html .= '
<div class="option_box_holder">
  Available Columns:
  <div class="option_box" id="unselected">';
    $html .= $this->draw_unselected_columns();
    $html .= '
  </div>
</div>';
    $html .= '
<div class="option_box_holder">
  Selected Columns:
  <div class="option_box" id="selected">';
    $html .= $this->draw_selected_columns();
    $html .= '
  </div>
</div>';
    return $html;
  }

  function draw_unselected_columns() {
    $statement = "select `column`.* from `column`
left join team_to_column on team_to_column.team_id = '".$this->data['team_id']."'
and team_to_column.column_id = `column`.column_id
where team_to_column.column_id is NULL 
order by `column`.column_order";
    $result = mysql_query($statement);
    $col = array();
    while ($row = mysql_fetch_array($result)) {
      $col[] = '<a href="javascript:select_column(\''.$row['column_id'].'\')">'.$row['column_header'].'</a>';
    }
    return implode("<br>", $col);
  }

  function draw_selected_columns() {
    $statement = "select * from `column`
left join team_to_column on team_to_column.team_id = '".$this->data['team_id']."'
and team_to_column.column_id = `column`.column_id
where team_to_column.column_id is not NULL 
order by `column`.column_order";
    $result = mysql_query($statement);
    $col = array();
    while ($row = mysql_fetch_array($result)) {
      $col[] = '<a href="javascript:deselect_column(\''.$row['column_id'].'\')">'.$row['column_header'].'</a>';
    }
    return implode("<br>", $col);
  }

  function auto_pick() {
    if ($this->data['team_autopick'] == 1) {
      return true;
    } else {
      return false;
    }
  }

  function skipped() {
    // See if this team has been skipped
    // first find the current pick
    $statement = "select pick_id
from pick where
pick.player_id is NULL
order by pick_id
limit 1";
    $row = mysql_fetch_array(mysql_query($statement));
    // Now find if our team has been skipped
    $statement = "select * from pick where pick_id < '".$row['pick_id']."' and
pick.team_id = '".$this->data['team_id']."' and
pick.player_id = '".kSkipPick."'";
    if (mysql_num_rows(mysql_query($statement))) {
      return true;
    } else {
      return false;
    }
  }

  function can_pick() {
    $statement = "select * from pick where player_id is NULL order by pick_id";
    $row = mysql_fetch_array(mysql_query($statement));
    if ($row['team_id'] == $this->data['team_id']) {
      return true;
    } else {
      return $this->skipped();
    }
  }

  function get_columns(&$col, &$list, $allow_sort = true) {
    // Generate the column list for the logged in user, values must be passed by referenc
    $statement = "select * from `column`, team_to_column where
team_to_column.team_id = '".$this->data['team_id']."' and
team_to_column.column_id = `column`.column_id
order by `column`.column_order";
    $result = mysql_query($statement);
    echo mysql_error();
    while ($row = mysql_fetch_array($result)) {
      $col_name = 'q'.md5($row['column_query']);
      $col[] = $row['column_query'].' '.$col_name;
      $list->set_header($col_name, $row['column_header'], $allow_sort, $allow_sort, $allow_sort);
      if ($row['column_style']) {
	$col[] = $row['column_style'].' '.$col_name.'_style';
	$list->set_cell_style($col_name, $col_name.'_style');
      }
      if ($row['column_exec']) {
	$list->set_exec($col_name, $row['column_exec']);
      }
      if ($row['column_date_format']) {
	$list->set_date_format($col_name, $row['column_date_format']);
      }
      if ($row['column_number_format']) {
	$list->set_number_format($col_name, $row['column_number_format']);
      }
    }
  }

  function get_comments() {
    return $this->data['team_comments'];
  }
}
?>