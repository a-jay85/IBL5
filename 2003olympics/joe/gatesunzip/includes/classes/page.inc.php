<?
/***************************************************************************
 *                                page.inc.php
 *                            -------------------
 *   begin                : Friday, Mar 28, 2008
 *   copyright            : (C) 2008 J. David Baker
 *   email                : me@jdavidbaker.com
 *
 *   $Id: page.inc.php,v 1.97 2011/06/11 12:08:57 jonb Exp $
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

define(kMaxMenuMessage, 55);

define(kRingSound, 1);
define(kClickSound, 2);
define(kBellSound, 3);

class page
{
    public function page($page)
    {
        $this->page = $page;
    }

    public function draw()
    {
        $start = microtime(true);
        global $settings;

        $function = "draw_" . $this->page;
        if ($_SESSION['message']) {
            $message = '
<div class="subbox" id="message"><h2>' . $_SESSION['message'] . '</h2></div>
<p>&nbsp;</p>';
            unset($_SESSION['message']);
        }
        $menu = $this->draw_top_menu();
        if (method_exists($this, $function)) {
            $content .= $this->$function();
        } else {
            $content .= file_get_contents("includes/html/" . $this->page . ".html");
        }
        $html = file_get_contents("includes/html/wrapper.html");
        if ($settings) {
            $html = str_replace("%league%", $settings->get_value(kSettingLeagueName), $html);
        } else {
            $html = str_replace("%league%", "SETUP", $html);
        }
        $html = str_replace("%message%", $message, $html);
        $html = str_replace("%menu%", $menu, $html);
        $html = str_replace("%content%", $this->content_wrapper($content), $html);
        $html = str_replace("%version%", kVersion, $html);
        $html = str_replace("%year%", kYear, $html);
        $end = microtime(true);
        if (defined("kDebug")) {
            $html .= '<div class="smalltext">' . number_format(($end - $start), 2) . ' Seconds</div>';
        }
        return $html;
    }

    public function draw_import_draft()
    {
        global $login;
        if ($login->is_admin()) {
            return file_get_contents("includes/html/import_draft.html");
        } else {
            return file_get_contents("includes/html/import_extractor.html");
        }
    }
    public function draw_import_draft_order()
    {
        return file_get_contents("includes/html/import_draft_order.html");
    }

    public function draw_import_mock_draft()
    {
        global $login;
        if ($login->is_admin()) {
            return file_get_contents("includes/html/import_mock_draft.html");
        } else {
            header("Location: ./");
            exit;
        }
    }

    public function content_wrapper($content)
    {
        global $login;
        if ($this->page == 'install') {
            // No menus on the login page
            $html = $content;
        } else {
            $html = file_get_contents("includes/html/tab_wrapper.html");
            $html = str_replace("%content%", $content, $html);
            $html = str_replace("%menu%", $this->draw_tab_menu(), $html);
        }
        return $html;
    }

    public function draw_top_menu()
    {
        global $login;
        if ($this->page != 'install') {
            // draw the top menu
            $html = '
<div class="top_menu">
  <ul>';
            // Get the current pick info
            $html .= '<li id="draft_status">' . $this->draft_status(false) . '</li>';
            $html .= '
    <li><a href="javascript:popup(\'analyzer.php\', \'analyzer\', 400, 200)">Analyzer Dump</a></li>';
            if ($login->team_id()) {
                $html .= '
    <li>Logged in as <b>' . $login->team_name() . '</b></li>
    <li><a href="logout.php">Log Out</a></li>';
            }
            $html .= '
  </ul>
</div>
<div style="clear: both"></div>
<script type="text/javascript">
  update_draft_status();
</script>';
        }
        return $html;
    }

    public function draft_status()
    {
        global $settings;
        global $login;
        $html .= '<i>' . date("g:i a T") . '</i> - ';
        // Do we have a chat invitation?
        $statement = "select * from chat_room, team
where team_2_id = '" . $login->team_id() . "' and team_2_arrived is NULL and
team.team_id = chat_room.team_1_id and
chat_room_ping > '" . date("Y-m-d H:i:s", strtotime("-30 seconds")) . "'";
        $row = mysql_fetch_array(mysql_query($statement));
        if ($row['chat_room_id']) {
            $message = "<b>Private chat invitation from " . $row['team_name'] . " -
<a href=\"javascript:popup('private_chat.php?chat_room_id=" . $row['chat_room_id'] . "', '_blank', 600, 450)\">Click Here</a></b>";
            $html .= '<div class="menu_chat">' . $message . '</div>';
            $this->extra_data = kRingSound;
        } else {
            // Get the most recent chat
            if (!$_SESSION['chat_time']) {
                $_SESSION['chat_time'] = date("Y-m-d H:i:s");
            }
            if (!$_SESSION['last_chat_id']) {
                $_SESSION['last_chat_id'] = 0;
            }
            // Don't do this if we are on the chat page
            $statement = "select * from chat, team where team.team_id = chat.team_id and
chat_time > '" . $_SESSION['chat_time'] . "'
and chat_id > '" . $_SESSION['last_chat_id'] . "'
and chat_room_id is NULL
order by chat_id limit 1";
            $row = mysql_fetch_array(mysql_query($statement));
            if ($row['chat_id']) {
                $_SESSION['last_chat_id'] = $row['chat_id'];
                $team = $row['team_name'];
                $message = $row['chat_message'];
                if (strlen($message) > kMaxMenuMessage) {
                    $message = substr($message, 0, kMaxMenuMessage) . '...';
                }
                if (!$_GET['chat_on']) {
                    $html .= '<div class="menu_chat"><b>' . $team . ':</b> ' . htmlentities($message) . '</div>';
                    //$this->extra_data = kClickSound;
                }
            }
        }
        $limit = $settings->get_value(kSettingPickTimeLimit);
        $col = array();
        $tables = array();
        $tables[] = "pick";
        $col[] = "pick.pick_id";
        $tables[] = "team";
        $wheres[] = "team.team_id = pick.team_id";
        $col[] = "team.team_id";
        $col[] = "team.team_name";
        $col[] = "team.team_clock_adj";
        $wheres[] = "pick.player_id is NULL";
        if ($limit) {
            $col[] = "timediff(date_add(pick.pick_time, interval (" . $limit . "*team.team_clock_adj) minute), '" . date("Y-m-d H:i:s") . "') on_clock";
        } else {
            $col[] = "timediff('" . date("Y-m-d H:i:s") . "', pick.pick_time) on_clock";
        }
        $statement = "select " . implode(",", $col) . " from " . implode(",", $tables) . " where " . implode(" and ", $wheres) . "
order by pick_id
limit 1";
        $row = mysql_fetch_array(mysql_query($statement));
        if ($row['pick_id']) {
            $pick = $row['pick_id'] % 32;
            if ($pick == 0) {
                $pick = 32;
            }
            list($hour, $min, $sec) = explode(":", $row['on_clock']);
            if ($settings->get_value(kSettingPickTimeLimit)) {
                if ($hour == 0 && $min <= 5) {
                    $style = "font-weight: bold; color: red;";
                    if ($min <= 1) {
                        $style .= " text-decoration: blink;";
                    }
                }
            }
            // If we are the team that is on the clock, send the bell sound if we haven't already
            if ($row['team_id'] == $login->team_id() &&
                $_SESSION['dinged_pick'][$row['pick_id']] < 3) {
                $this->extra_data = kBellSound;
                $_SESSION['dinged_pick'][$row['pick_id']] = $_SESSION['dinged_pick'][$row['pick_id']] + 1;
            }
            $html .= '
On the clock: ' . $row['team_name'] . ' (round ' . ceil(($row['pick_id']) / 32) . ', pick ' . $pick . ',
<span style="' . $style . '">' . $hour . ':' . $min . '</span>)';
            $this->on_clock = $row['team_name'];
            $this->on_clock_team_id = $row['team_id'];
            $this->on_clock_pick = $row['pick_id'];
        } else {
            // We are either done or halted
            $statement = "select count(*) num from pick where player_id = '" . kDraftHalt . "'";
            $row = mysql_fetch_array(mysql_query($statement));
            if ($row['num']) {
                // Draft is halted
                $html .= '
Draft is stopped';
            } else {
                $html .= '
Draft is complete';
            }
        }
        return $html;
    }

    public function draw_tab_menu()
    {
        global $login;
        $menu = array();
        if (!$login->team_id()) {
            // Guest
            $menu['Log In'] = 'login_page.php';
            $menu['Register'] = 'register.php';
            $menu['Selections'] = "selections.php";
        } else {
            if ($login->is_admin()) {
                $menu['Import Draft'] = 'import_draft.php';
                $menu['Mock Setup'] = 'import_mock_draft.php';
                $menu['Scout Weights'] = 'scout_weights.php';
                $menu['Users'] = 'users.php';
                $menu['Rollback'] = 'rollback.php';
            }
            $menu['Players'] = 'players.php';
            if (!$login->is_admin()) {
                $menu['Import Extractor File'] = 'import_draft.php';
                $menu['Import Priority List'] = 'import_draft_order.php';
                $menu['Priority'] = 'priority.php';
                $menu['Notes'] = 'notes.php';
            }
            $menu['Options'] = 'options.php';
            $menu['Selections'] = 'selections.php';
            $statement = "select * from mock_draft";
            $menu['Chat'] = 'chat.php';
        }
        // Everyone can see the mock draft if it's there
        $statement = "select * from mock_draft";
        if (mysql_num_rows(mysql_query($statement))) {
            $menu['Mock Draft'] = 'mock_draft.php';
        }
        $html .= '
  <ul>';
        foreach ($menu as $title => $link) {
            if (preg_match("/\/" . $link . "$/", $_SERVER['SCRIPT_NAME'])) {
                $class = ' class="nav_active"';
            } else {
                $class = "";
            }
            $html .= '
    <li><a href="' . $link . '"' . $class . '><span' . $class . '>' . $title . '</span></a></li>';
        }
        $html .= '
  </ul>';
        return $html;
    }

    public function draw_install()
    {
        $html = file_get_contents("includes/html/install.html");
        // Check for write access
        if (!is_writable("includes")) {
            $report = '<h3>Unable to write to the includes directory.</h3>
<p><a href="./">Click here</a> to try again.</p>';
        } elseif ($_SESSION['error']) {
            $report = "<P>" . $_SESSION['error'];
            unset($_SESSION['error']);
        } else {
            $report = "<h3>Writing to the includes directory was successful.</h3>";
        }
        $html = str_replace("%report%", $report, $html);
        return $html;
    }

    public function draw_players()
    {
        global $login;
        if (!$login->team_id()) {
            header("Location: ./");
            exit;
        }
        // See if we have imported our own data
        $statement = "select * from team_player_to_attribute where team_id = '" . $login->team_id() . "' limit 1";
        if (mysql_num_rows(mysql_query($statement))) {
            $uploaded = true;
        } else {
            $uploaded = false;
        }
        $list = new table_list();
        $list->set_form_button("position_id", $_GET['position_id'], "hidden");
        $list->set_form_button("show_attributes", $_GET['show_attributes'], "hidden");
        $list->set_form_button("filter_overrated", $_GET['filter_overrated'], "hidden");
        $tables[] = "player";
        $col[] = "player.player_id";
        $list->set_header("player_id", "#");
        $col[] = "
concat('<a href=\"show_player.php?player_id=', player.player_id, '\">', player.player_name, '</a>') player_name";
        $list->set_header("player_name", "Name");
        $tables[] = "position";
        $wheres[] = "position.position_id = player.position_id";
        $tables[] = "combine_ratings";
        $wheres[] = "combine_ratings.position_id = position.position_id";
        // If we have a position_id, show the attributes
        if ($_GET['position_id'] && $_GET['show_attributes']) {
            $statement = "select * from attribute, position_to_attribute where
attribute.attribute_id = position_to_attribute.attribute_id and
position_to_attribute.position_id = '" . $_GET['position_id'] . "'
order by position_to_attribute_order";
            $result = mysql_query($statement);
            echo mysql_error();
            $i = 0;
            while ($row = mysql_fetch_array($result)) {
                $alias = "pa_$i";
                if ($uploaded) {
                    $tables[] = "team_player_to_attribute $alias";
                    $wheres[] = "$alias.team_id = '" . $login->team_id() . "'";
                } else {
                    $tables[] = "player_to_attribute $alias";
                }
                $wheres[] = "$alias.player_id = player.player_id";
                $wheres[] = "$alias.attribute_id = '" . $row['attribute_id'] . "'";
                if ($row['attribute_id'] == 1) {
                    $col[] = "$alias.player_to_attribute_low score_low_$i";
                    $list->set_header("score_low_$i", $row['attribute_abb']);
                } else {
                    $col[] = "$alias.player_to_attribute_low score_low_$i";
                    $list->set_header("score_low_$i", $row['attribute_abb'] . ' (L)');
                    $col[] = "$alias.player_to_attribute_high score_high_$i";
                    $list->set_header("score_high_$i", $row['attribute_abb'] . ' (H)');
                }
                $i++;
            }
        } else {
            // Get the columns from the database
            $login->get_columns($col, $list);
        }
        if (!$login->is_admin()) {
            // Make selection if we can
            if ($login->team_id() == $this->on_clock_team_id || $login->skipped()) {
                $col[] = "player.player_id make_pick";
                $list->set_header("make_pick", "Make Pick");
                $list->set_data_format("make_pick", '<a href="make_pick.php?player_id=%data%">Make Pick<a>');
            }
            // Comments
            $joins[] = "left join player_comments on player_comments.player_id = player.player_id and
player_comments.team_id = '" . $login->team_id() . "'";
            $col[] = "player_comments.player_comments_text";
            $list->set_header("player_comments_text", "Comments");
            $list->set_data_format("player_comments_text", '
<div id="player_comments_text_%id%">
<div id="player_comments_%id%">%data%</div>
<br><a href="javascript:edit_comments(\'%id%\')" class="smalltext">(edit)</a></div>
<div id="player_comments_text_edit_%id%" style="display: none">
<input type="text" id="player_comments_text_edit_box_%id%" size="7" value="%data%"><br>
<a href="javascript:save_comments(\'%id%\')" class="smalltext">(Save)</a>
<a href="javascript:cancel_comments(\'%id%\')" class="smalltext">(Cancel)</a>
</div>
<div id="player_comments_progress_%id%" style="display: none; text-align: center;"><img src="images/pinwheel.gif"></div>');
        }

        if ($_GET['position_id']) {
            $wheres[] = "player.position_id = '" . $_GET['position_id'] . "'";
        }
        $list->append_query(array("position_id" => $_GET['position_id'],
            "show_attributes" => $_GET['show_attributes'],
            "filter_overrated" => $_GET['filter_overrated']));

        // Do not show selected players
        $joins[] = "left join pick on pick.player_id = player.player_id";
        $wheres[] = "pick.pick_id is NULL";

        // Create the form to make the pick
        $html .= '
<h3>Players</h3>';
        if ($login->is_admin()) {
            // Admin can make the pick for the current team...
            $list->set_form("make_selection.php", "post");
            if ($this->on_clock) {
                $list->set_form_button("make_selection", "Select for " . $this->on_clock);
                $col[] = "player.player_id select_me";
                $list->set_header("select_me", "Select for " . $this->on_clock, false, false, false);
                $list->set_data_format("select_me", '<input type="radio" name="selection" value="%data%">');
                $list->set_form_button("pick_id", $this->on_clock_pick, "hidden");
            }
            $html .= '
<p>Use this page to make a selection for a team that is on the clock but has not made their selection.
<p>If you filter by position, you can view the bar values by turning the checkbox on.';
        } else {
            if ($login->pick_method() == kPlayerQueue || $login->pick_method() == kPlayerThenBPA) {
                // Show the selection order
                $list->set_link('', "player_id");
                $joins[] = "left join selection on selection.team_id = '" . $login->team_id() . "' and
selection.player_id = player.player_id";
                $col[] = "if (selection.selection_priority is NULL, '', ' checked') is_selected";
                $list->set_header("is_selected", "Selected", false, false, false);
                $list->set_form("set_selections.php", "post");
                $list->set_form_button("make_selection", "Set Selections");
                $list->set_data_format("is_selected", '
<input type="hidden" name="player_id_selection[]" value="%id%">
<input type="checkbox" name="select[%id%]"%data%>');
                $html .= '
<p>Use this page to select players you would like to draft.  You must go to the "Priority" page
to set the priority before anyone will be selected.  Be sure to save your selections by clicking on "Set Selections" before
changing the page or rows per page.  The concept is that you select the players you want to draft
and then set the priority that you want to draft them.  When your turn comes up, the system will
select the highest priority player in your queue if there is one.  If you select enough players, you
could even draft several rounds before having to adjust your selection queue.';
                if (!$login->data['team_multipos']) {
                    $html .= '
When a player has been selected for you, any players with the same position will receive a priority of "0"
(essentially removing them from the active queue)
but will remain in your selection queue.';
                }
            } elseif ($login->pick_method() == kBPAQueue) {
                $html .= '
<p>You are currently choosing players by the "Best at position" method.  You may only view available players here.
To choose specific players, change your preferences in the "Options" tab.';
            }
        }

        $html .= '
<form method="get" action="players.php">
  <table class="data">
    <tr>
      <td align="right" class="light">Show:</td>
      <td class="light">
        <select name="position_id">
          <option value="">All</option>';
        $position = new position('');
        $html .= $position->option_list();
        if ($_GET['show_attributes']) {
            $checked = " checked";
        } else {
            $checked = "";
        }
        $html .= '
        </select>
      </td>
      <td align="center" class="light">
        <input type="checkbox" name="show_attributes"' . $checked . '> Show Bar Values
      </td>';
        if ($_GET['filter_overrated']) {
            $checked = " checked";
            $wheres[] = "(team_player.player_impression is NULL or team_player.player_impression not like '%Overrated')";
        } else {
            $checked = "";
        }
        $html .= '
      <td align="center" class="light">
        <input type="checkbox" name="filter_overrated"' . $checked . '> Filter Overrated
      </td>
      <td class="light">
        <input type="submit" value="Filter">
      </td>
    </tr>
  </table>
</form>';

        $col[] = "
CONCAT_WS(
'; ',
if (team_player.player_interviewed = 'Yes', 'background-color: #ff8', NULL)) style";
        $list->set_style('style');

        $joins[] = "left join team_player on team_player.player_id = player.player_id and
team_player.team_id = '" . $login->team_id() . "'";

        $statement = "select " . implode(",", $col) . " from (" . implode(",", $tables) . ") " . implode(" ", $joins) . "
where " . implode(" and ", $wheres);
        $html .= $list->draw_list($statement);
        return $html;
    }

    public function draw_selections()
    {
        global $login;
        $html .= '
<h3>Draft Selections</h3>';
        if ($login->is_admin()) {
            $html .= '
<p>You may change the team for any pick from here.  You may also skip a pick if your rules are set so that
if a team does not pick in the allotted time their pick is skipped.  A team that has been
skipped will be able to make their selection at any time, but the next team goes on the clock.
If you skip a pick you will have the ability to "unskip" it as well.</p>';
        }
        $list = new table_list();
        $tables[] = "pick";
        $col[] = "pick_id";
        $list->set_exec("pick_id", "calculate_pick");
        $list->set_header("pick_id", "Pick");
        $tables[] = "team";
        $wheres[] = "team.team_id = pick.team_id";
        if ($_GET['team_id']) {
            $wheres[] = "team.team_id = '" . $_GET['team_id'] . "'";
            $list->append_query(array("team_id" => $_GET['team_id']));
        }
        $joins[] = "left join selection on selection.team_id = team.team_id and selection.selection_priority != 0";
        $joins[] = "left join bpa on bpa.team_id = team.team_id";
        $col[] = "if (pick.player_id = '" . kSkipPick . "', concat(team.team_name, ' (Skipped)'),
if (team.team_autopick = '1' and team.pick_method_id = '" . kPlayerQueue . "' and selection.team_id is not null,
  concat(team.team_name, ' *', if (team_autopick_wait > 0, concat(' (', team_autopick_wait, ' min)'), '')),
  if (team.team_autopick = '1' and team.pick_method_id = '" . kBPAQueue . "' and bpa.team_id is not null,
    concat(team.team_name, ' *', if (team_autopick_wait > 0, concat(' (', team_autopick_wait, ' min)'), '')),
    if (team.team_autopick = '1' and team.pick_method_id = '" . kScoutPick . "',
      concat(team.team_name, ' *', if (team_autopick_wait > 0, concat(' (', team_autopick_wait, ' min)'), '')),
      if (team.team_autopick = '1' and team.pick_method_id = '" . kPlayerThenBPA . "' and (selection.team_id is not null or bpa.team_id is not null),
        concat(team.team_name, ' *', if (team_autopick_wait > 0, concat(' (', team_autopick_wait, ' min)'), '')),
          if (team.team_clock_adj = '0.0',
            concat(team.team_name, ' *'),
team.team_name)))))) team_name";
        $list->set_header("team_name", "Team");
        if ($login->is_admin()) {
            $col[] = "pick_id chng";
            $list->set_header("chng", "Change Team", false, false, false);
            $list->set_data_format("chng", '<a href="edit_pick.php?pick_id=%data%">&raquo; change team</a>');
        } elseif ($login->team_id()) {
            // Allow teams to give away their picks
            $col[] = "if (pick.team_id = '" . $login->team_id() . "' and (pick.player_id is NULL or pick.player_id = '" . kSkipPick . "' or
pick.player_id = '" . kDraftHalt . "'),
concat('<a href=\"edit_pick.php?pick_id=', pick.pick_id, '\">&raquo; change team</a>'), NULL) chng";
            $list->set_header("chng", "Change Team", false, false, false);
        }
        $joins[] = "left join player on player.player_id = pick.player_id";
        $col[] = "if (pick.player_id,
concat('<a href=\"show_player.php?player_id=', player.player_id, '\">', player.player_name, '</a>'),
NULL) player_name";
        $list->set_header("player_name", "Name");
        $joins[] = "left join position on position.position_id = player.position_id";
        $col[] = "position.position_name";
        $list->set_header("position_name", "Pos");
        $col[] = "player.player_school";
        $list->set_header("player_school", "School");
        // Bold the pick if it hasn't happened yet
        $col[] = "if (pick.player_id is NULL, 'font-weight: bold;',
if (pick.player_id = '" . kSkipPick . "', 'font-style: italic; font-weight: bold', '')) style";
        $list->set_style("style");
        // Allow the admin to mark a pick as being skipped
        if ($login->is_admin()) {
            $col[] = "if (pick.player_id is NULL,
concat('<a href=\"skip_pick.php?pick_id=', pick.pick_id, '\">Skip</a>'),
if (pick.player_id = '" . kSkipPick . "', concat('<a href=\"unskip_pick.php?pick_id=', pick.pick_id, '\">Unskip</a>'), NULL))
skip";
            $list->set_header("skip", "Skip Pick");
        }

        $html .= '
<form method="get" action="selections.php">
  <table class="data">
    <tr>
      <td align="right" class="light">Show:</td>
      <td class="light">
        <select name="team_id">
          <option value="">All</option>';
        $statement = "select * from team where team_id != '" . kAdminUser . "' and team_name != 'xxx' order by team_name";
        $result = mysql_query($statement);
        echo mysql_error();
        while ($row = mysql_fetch_array($result)) {
            if ($row['team_id'] == $_GET['team_id']) {
                $selected = " selected";
            } else {
                $selected = '';
            }
            $html .= '
          <option value="' . $row['team_id'] . '"' . $selected . '>' . $row['team_name'] . '</option>';
        }
        $html .= '
        </select>
      </td>
      <td class="light">
        <input type="submit" value="Filter">
      </td>
    </tr>
  </table>
</form>';

        $joins[] = "left join team_player on team_player.player_id = player.player_id and
team_player.team_id = '" . $login->team_id() . "'";

        $statement = "select " . implode(",", $col) . " from (" . implode(",", $tables) . ") " . implode(" ", $joins) . "
where " . implode(" and ", $wheres) . " group by pick_id";
        $html .= $list->draw_list($statement);
        $html .= '
<p class="smalltext">* Pick will fire immediately.</p>';
        return $html;
    }

    public function draw_mock_draft()
    {
        global $login;
        if ($login->team_id()) {
            $html .= '
<h3>Mock Draft</h3>
<p align="center"><a href="mock_draft_text.php" target="_blank">Text for forum (round 1)</a></p>';
        }
        $list = new table_list();
        $tables[] = "mock_draft";
        $col[] = "mock_draft.pick_id";
        $list->set_exec("pick_id", "calculate_pick");
        $list->set_header("pick_id", "Pick");
        $tables[] = "team";
        $wheres[] = "team.team_id = mock_draft.team_id";
        if ($_GET['team_id']) {
            $wheres[] = "team.team_id = '" . $_GET['team_id'] . "'";
            $list->append_query(array("team_id" => $_GET['team_id']));
        }
        $col[] = "team.team_name";
        $list->set_header("team_name", "Team");
        $joins[] = "left join player on player.player_id = mock_draft.player_id";
        $col[] = "concat('<a href=\"show_player.php?player_id=', player.player_id, '\">', player.player_name, '</a>') player_name";
        $list->set_header("player_name", "Name");
        $joins[] = "left join position on position.position_id = player.position_id";
        $col[] = "position.position_name";
        $list->set_header("position_name", "Pos");
        $col[] = "player.player_school";
        $list->set_header("player_school", "School");
        $col[] = "mock_draft.mock_draft_commentary";
        $list->set_header("mock_draft_commentary", "Comments", false, false, false);

        $tables[] = "pick";
        $wheres[] = "pick.pick_id = mock_draft.pick_id";
        $tables[] = "team actual_team";
        $wheres[] = "actual_team.team_id = pick.team_id";
        $col[] = "actual_team.team_name actualteam";
        $joins[] = "left join player selected_player on selected_player.player_id = pick.player_id";
        $joins[] = "left join position selected_position on selected_position.position_id = selected_player.position_id";
        $col[] = "concat('<a href=\"show_player.php?player_id=', selected_player.player_id, '\">', selected_player.player_name, '</a>') selected_player_name";
        $list->set_header("selected_player_name", "Actual Selection");
        $col[] = "selected_position.position_name selected_position";
        $list->set_header("selected_position", "Pos");
        $col[] = "selected_player.player_school selected_school";
        //$list->set_header("selected_school", "School");
        $list->set_header("actualteam", "Team");

        $html .= '
<form method="get" action="mock_draft.php">
  <table class="data">
    <tr>
      <td align="right" class="light">Show:</td>
      <td class="light">
        <select name="team_id">
          <option value="">All</option>';
        $statement = "select * from team where team_id != '" . kAdminUser . "' and team_name != 'xxx' order by team_name";
        $result = mysql_query($statement);
        echo mysql_error();
        while ($row = mysql_fetch_array($result)) {
            if ($row['team_id'] == $_GET['team_id']) {
                $selected = " selected";
            } else {
                $selected = '';
            }
            $html .= '
          <option value="' . $row['team_id'] . '"' . $selected . '>' . $row['team_name'] . '</option>';
        }
        $html .= '
        </select>
      </td>
      <td class="light">
        <input type="submit" value="Filter">
      </td>
    </tr>
  </table>
</form>';

        $statement = "select " . implode(",", $col) . " from (" . implode(",", $tables) . ") " . implode(" ", $joins) . "
where " . implode(" and ", $wheres);
        $html .= $list->draw_list($statement);
        $html .= '
<p class="smalltext">* Team has players queued for autopick</p>';
        return $html;
    }

    public function draw_priority()
    {
        global $login;
        if (!$login->team_id()) {
            header("Location: ./");
            exit;
        }
        $html .= '
<h3>Selection Priority</h3>';
        if (!$login->auto_pick()) {
            $html .= '
<div class="subbox"><h3><b>NOTE:</b> Your auto-picks are turned off.  Picks must be made manually.
To turn auto-pick back on, you must go to the "Options" tab and turn picks on.</h3></div>';
        }
        if ($login->pick_method() == kPlayerQueue) {
            $html .= $this->draw_player_queue();
        } elseif ($login->pick_method() == kBPAQueue) {
            $html .= $this->draw_BPAQueue();
        } elseif ($login->pick_method() == kPlayerThenBPA) {
            $html .= '
<p>You are set to use your player queue then BPA queue.  If your player queue is empty, then the selection
will be made using your BPA queue.
<div id="player_queue">
  <div class="tab_menu">
    <ul>
      <li><a href="javascript:swap_divs(\'player_queue\', \'BPA_queue\')" class="nav_active"><span>Player Queue</span></a></li>
      <li><a href="javascript:swap_divs(\'BPA_queue\', \'player_queue\')"><span>BPA Queue</span></a></li>
    </ul>
  </div>
  <div class="tab_content">';
            $html .= $this->draw_player_queue();
            $html .= '
  </div>
</div>
<div id="BPA_queue" style="display: none">
  <div class="tab_menu">
    <ul>
      <li><a href="javascript:swap_divs(\'player_queue\', \'BPA_queue\')"><span>Player Queue</span></a></li>
      <li><a href="javascript:swap_divs(\'BPA_queue\', \'player_queue\')" class="nav_active"><span>BPA Queue</span></a></li>
    </ul>
  </div>
  <div class="tab_content">';
            $html .= $this->draw_BPAQueue();
            $html .= '
  </div>
</div>';
            if ($_GET['queue'] == 'bpa') {
                $html .= '
<script language="javascript">
  swap_divs(\'BPA_queue\', \'player_queue\');
</script>';
            }
        } else {
            $html .= '
<div class="subbox"><h3>You are set to "scout pick."  To change your pick method, go to the "Options" tab.</h3></div>';
        }
        return $html;
    }

    public function draw_team_queue()
    {
        global $login;
        if (!$login->is_admin()) {
            header("Location: ./");
            exit;
        }
        $team = new team($_GET['team_id']);
        $html .= '
<h3>Selection Priority for ' . $team->team_name() . '</h3>';
        if ($team->pick_method() == kPlayerQueue) {
            $html .= $this->draw_player_queue($_GET['team_id']);
        } else {
            $html .= $this->draw_BPAQueue($_GET['team_id']);
        }
        return $html;
    }

    public function draw_player_queue($team_id = '')
    {
        global $login;
        if (!$login->team_id()) {
            header("Location: ./");
            exit;
        }
        if (!$team_id) {
            $html .= '
<p>Set the priority of your selections here by dragging each player to rearrange.  You can drag players
from the inactive list to the active list and vice versa.
As long as you have players in your active list,
the system will pick your player from the TOP of your list
when you are on the clock.  Once a position has been picked, all the other players of the same position
will have their priority reset to zero (configurable in your options tab),
so you will not pick two players of the same position without
intentionally resetting the priority value.  To speed up the draft, you can enter many rounds\' worth of
players at once.</p>
<p>Use the comments column to make comments about each player.</p>';
        }

        // First the inactive list
        $html .= '<h3>Inactive List: <a class="smalltext" href="clear_inactives.php">Clear Inactives</a></h3>';
        $html .= $this->draw_priority_list(false, $team_id);
        // Then the active list
        $html .= '<h3>Active List (<span id="active_count"></span>):</h3>';
        if (!$team_id) {
            $html .= '
<form id="quick_add_form" action="quick_add.php" method="post">
  <p>Quick Add: <input id="quick_add" name="quick_add"></p>
</form>';
        }
        $html .= $this->draw_priority_list(true, $team_id);

        return $html;
    }

    public function draw_priority_list($actives, $team_id)
    {
        global $login;
        $joins = array();
        $list = new table_list(false);
        $list->set_show_totals(false);
        $tables[] = "selection";
        $col[] = "selection.player_id";
        $list->set_header("player_id", "#", false, false, false);
        $list->set_link("", "player_id");
        if ($team_id) {
            $wheres[] = "selection.team_id = '$team_id'";
        } else {
            $wheres[] = "selection.team_id = '" . $login->team_id() . "'";
        }
        $tables[] = "player";
        $wheres[] = "player.player_id = selection.player_id";
        $col[] = "
concat('<a href=\"show_player.php?player_id=', player.player_id, '\">', player.player_name, '</a><input type=\"hidden\" name=\"player_id[]\" value=\"', player.player_id, '\"') player_name";
        $list->set_header("player_name", "Name", false, false, false);
        $tables[] = "position";
        $wheres[] = "position.position_id = player.position_id";
        $tables[] = "combine_ratings";
        $wheres[] = "combine_ratings.position_id = position.position_id";
        $list->set_class("priority_list");
        $col[] = "selection_priority";
        if ($actives) {
            $list->set_id('priority_list');
            $wheres[] = "selection_priority != 0";
        } else {
            $list->set_id('zero_priority');
            $wheres[] = "selection_priority = 0";
            $list->set_class("zero_priority");
        }
        $login->get_columns($col, $list, false);
        $list->clear_order_by();
        $list->add_order_by('selection_priority');

        if (!$team_id) {
            // If we have autopick off and it's our turn, have a "choose player" button
            if (!$login->auto_pick() && ($login->team_id() == $this->on_clock_team_id || $login->skipped())) {
                $col[] = "player.player_id make_pick";
                $list->set_header("make_pick", "Make Pick", false, false, false);
                $list->set_data_format("make_pick", '<a href="make_pick.php?player_id=%data%">Make Pick<a>');
            }

            // Comments
            $joins[] = "left join player_comments on player_comments.player_id = player.player_id and
player_comments.team_id = '" . $login->team_id() . "'";
            $col[] = "player_comments.player_comments_text";
            $list->set_header("player_comments_text", "Comments", false, false, false);
            $list->set_data_format("player_comments_text", '
<div id="player_comments_text_%id%">
<div id="player_comments_%id%">%data%</div>
<br><a href="javascript:edit_comments(\'%id%\')" class="smalltext">(edit)</a></div>
<div id="player_comments_text_edit_%id%" style="display: none">
<input type="text" id="player_comments_text_edit_box_%id%" size="7" value="%data%"><br>
<a href="javascript:save_comments(\'%id%\')" class="smalltext">(Save)</a>
<a href="javascript:cancel_comments(\'%id%\')" class="smalltext">(Cancel)</a>
</div>
<div id="player_comments_progress_%id%" style="display: none; text-align: center;"><img src="images/pinwheel.gif"></div>');

            $col[] = "player.player_id del";
            $list->set_header("del", "Delete", false, false, false);
            $list->set_data_format("del", '<a href="javascript:delete_player_from_queue(\'%data%\')" id="delete_%data%"><img src="images/icons/user-trash.png" border="0"></a>');
        }

        $joins[] = "left join team_player on team_player.player_id = player.player_id and
team_player.team_id = '" . $login->team_id() . "'";

        $statement = "select " . implode(",", $col) . " from (" . implode(",", $tables) . ") " . implode(" ", $joins) . "
where " . implode(" and ", $wheres);
        $html .= $list->draw_list($statement);
        return $html;
    }

    public function draw_BPAQueue($team_id = '')
    {
        global $login;
        if (!$login->team_id()) {
            header("Location: ./");
            exit;
        }
        if (!$team_id) {
            $html .= '
<p>Set the priority of your selections here.  Lower numbers mean higher priority: <b>#10 will be selected before #20.</b>
You may have multiple entries of the same position.  Once one of the enties is used, it will be removed from
your queue.</p>
<p>If you are in a full league draft, you may set the max experience for the player.  In a rookie draft, this setting
will not have any effect.</p>';
        }
        $list = new table_list();
        $tables[] = "bpa";
        $col[] = "bpa.bpa_id";
        $list->set_link("", "bpa_id");
        if ($team_id) {
            $wheres[] = "bpa.team_id = '$team_id'";
        } else {
            $wheres[] = "bpa.team_id = '" . $login->team_id() . "'";
        }
        $tables[] = "position";
        $wheres[] = "position.position_id = bpa.position_id";
        $col[] = "position.position_name";
        $list->set_header("position_name", "Position");
        $joins[] = "left join attribute on attribute.attribute_id = bpa.attribute_id";
        $col[] = "if (bpa.attribute_id = '-1', 'Adjusted Grade',
                  if (bpa.attribute_id = '-2', 'Future Rating',
                      if (bpa.attribute_id = '-3', 'Current Rating',  attribute.attribute_name))) attribute_name";
        $list->set_header("attribute_name", "Best Player Qualification");
        $col[] = "bpa.bpa_priority";
        $list->set_header("bpa_priority", "Priority", 'asc');
        $col[] = "bpa.bpa_max_experience";
        $list->set_header("bpa_max_experience", "Max Experience");
        if (!$team_id) {
            $list->set_data_format("bpa_priority", '
<input type="text" name="bpa_priority[%id%]" value="%data%" size="3">');
            $col[] = "bpa.bpa_id del";
            $list->set_header("del", "Delete", false, false, false);
            $list->set_data_format("del", '<input type="checkbox" name="delete[]" value="%data%">');

            $list->set_form("bpa_set.php", "post");
            $list->set_form_button("set_bpa", "Save List");
        }

        $statement = "select " . implode(",", $col) . " from (" . implode(",", $tables) . ")
" . implode(" ", $joins) . "
where " . implode(" and ", $wheres);
        $html .= $list->draw_list($statement);

        // Ability to add a new one
        $html .= '
<hr>
<p>Add to the list:</p>';
        $html .= '
<form method="post" action="add_bpa.php">
  <table class="data">
    <tr>
      <td class="light" align="right" width="15%">Position:</td>
      <td class="light" width="15%">
        <select name="position_id" onchange="bpa_pos_change()" id="position_id">
          <option>Select a position</option>';
        $position = new position('');
        $html .= $position->option_list();
        $html .= '
        </select>
      </td>
      <td class="light" align="right" width="15%">Qualification</td>
      <td class="light" width="15%">
        <div id="bpa_select">
          <select name="attribute_id">
          </select>
        </div>
      </td>
      <td class="light" align="right">Max Experience:</td>
      <td class="light">
        <select name="bpa_max_experience">
          <option value="">No Limit</option>';
        $i = 1;
        while ($i <= 24) {
            $html .= '
          <option value="' . $i . '">' . $i . '</option>';
            $i++;
        }
        $html .= '
        </select>
      </td>
      <td class="light"><input type="submit" value="Add">
    </tr>
  </table>
</form>';

        return $html;
    }

    public function draw_edit_pick()
    {
        global $login;
        if (!$login->team_id()) {
            header("Location: ./");
            exit;
        }
        $pick_id = $_GET['pick_id'];
        $pick = new pick($pick_id);
        return $pick->draw_edit();
    }

    public function draw_options()
    {
        global $login;
        if (!$login->team_id()) {
            header("Location: ./");
            exit;
        }
        return $login->draw_options();
    }

    public function draw_users()
    {
        global $login;
        if (!$login->is_admin()) {
            header("Location: players.php");
            exit;
        }
        $html .= '
<h3>Team Admin</h3>
<p>This is the list of teams and whether or not they have a password.  To clear the password, turn the checkbox off.
This will allow (require) the team to re-register.  They will not lose any of their selections.
To lock out a team that does not have a password (for example, for an AI team), turn the checkbox on.  If you turn
a password on for a team that does not have a password, a random password will be generated which no one will know.
If you turn the password off for a team and then turn it back on, it will effectively lock that team out.</p>
<p>If you are using the pick time limit, you can adjust the percentage of time each team has to make a pick on the clock.
The value is in a percent, 100% will be the full time allowed, 50% will be half the time (a setting of one hour
would only allow thirty minutes for this team to pick), 1000% will be ten times (a setting of one hour would allow
ten hours), etc.</p>
<p>Clearing a password will set the clock adjustment to 0%, as will creating a random password.  When a team registers,
the clock adjustment will reset to 100%.</p>';
        $list = new table_list();
        $list->set_form("users_run.php", "post");
        $list->set_form_button("save", "Save");
        $tables[] = "team";
        $wheres[] = "team.team_id != '" . kAdminUser . "'";
        $wheres[] = "team.team_name != 'xxx'";
        $col[] = "team.team_id";
        $list->set_link("", "team_id");
        $col[] = "team.team_name";
        $list->set_header("team_name", "Team Name");
        $joins[] = "left join pick on pick.team_id = team.team_id";
        $col[] = "if(count(pick.pick_expired), count(distinct(concat(pick.pick_expired, ' ', pick.pick_id))), 0) exp";
        $list->set_header("exp", "Times Expired");
        $joins[] = "left join selection on selection.team_id = team.team_id and selection.selection_priority != '0'";
        $joins[] = "left join bpa on bpa.team_id = team.team_id";
        $col[] = "
if (team.team_autopick = '1' and team.pick_method_id = '" . kPlayerQueue . "' and selection.team_id is not null,
  concat('Player Queue: ',count(distinct(selection.player_id)),' players'),
  if (team.team_autopick = '1' and team.pick_method_id = '" . kBPAQueue . "' and bpa.team_id is not null,
    concat('BPA Queue: ',count(bpa.team_id),' selections'),
    if (team.team_autopick = '1' and team.pick_method_id = '" . kScoutPick . "',
      concat('Scout Pick'),
NULL))) selections";
        $list->set_header("selections", "Queue info");
        $col[] = "concat('<a href=\"team_queue.php?team_id=', team.team_id, '\">View Queue</a>') queue";
        $list->set_header("queue", "View Queue");
        $col[] = "team.team_email";
        $list->set_header("team_email", "E-mail");
        $col[] = "floor(team.team_clock_adj*100) team_clock_adj";
        $list->set_header("team_clock_adj", "Adjust clock");
        $list->set_data_format("team_clock_adj", "<input type=\"text\" name=\"team_clock_adj[%id%]\" value=\"%data%\" size=\"5\"> %");

        //$list->set_header("team_clock_adj", "Adjust clock");
        $col[] = "concat('
<input type=\"hidden\" name=\"team_id[]\" value=\"', team.team_id, '\">
<input type=\"checkbox\" name=\"has_password[', team.team_id, ']\"', if (team.team_password is NULL, '', ' checked'), '> Has Password') password";
        $list->set_header("password", "Password", false, false, false);
        $col[] = "concat('
<input type=\"checkbox\" name=\"team_autopick[', team.team_id, ']\"', if (team.team_autopick = '0', '', ' checked'), '> Autopick On') autopick";
        $list->set_header("autopick", "Autopick on", false, false, false);

        $statement = "select " . implode(",", $col) . " from (" . implode(",", $tables) . ") " . implode(" ", $joins) . "
where " . implode(" and ", $wheres) . " group by team.team_id";
        $html .= $list->draw_list($statement);
        $html .= '
<h2>Add Team</h2>
<p>If you need to add a team, for instance if a team didn\'t have any draft picks, enter the 3-character team name below:</p>
<form method="post" action="add_team.php">
  <table align="center">
    <tr>
      <td>Team Name:</td>
      <td><input type="text" name="team_name" maxlength="3" size="3"></td>
      <td><input type="submit" value="Add Team"></td>
    </tr>
  </table>
</form>';
        return $html;
    }

    public function draw_rollback()
    {
        // Allows the draft to be rolled back to a pick
        global $login;
        if (!$login->is_admin()) {
            header("Location: ./");
            exit;
        }
        $html .= '
<h3>Draft Rollback</h3>
<p>If you need to roll the draft back to a certain pick, you can do so here.  Please note that you should
communicate this to your teams, as any player that was drafted will not be returned to the queue for any team.
Teams that have entered their e-mail address will get an e-mail listing the players that are back on the board,
and the draft will be halted to give teams a chance to rebuild their queue (go to the "options" tab to start the
draft again).</p>';
        $html .= '
<form method="post" action="rollback_run.php">
  <table class="data">
    <tr>
      <td class="light" align="right">Roll back draft to:</td>
      <td class="light">
        <select name="pick_id">';
        $tables[] = "pick";
        $col[] = "pick.pick_id";
        $tables[] = "team";
        $wheres[] = "team.team_id = pick.team_id";
        $col[] = "team.team_name";
        $tables[] = "player";
        $wheres[] = "player.player_id = pick.player_id";
        $col[] = "player.player_name";
        $tables[] = "position";
        $wheres[] = "position.position_id = player.position_id";
        $col[] = "position.position_name";

        $statement = "select " . implode(",", $col) . " from " . implode(",", $tables) . " where " . implode(" and ", $wheres) . '
order by pick_id desc';
        $result = mysql_query($statement);
        while ($row = mysql_fetch_array($result)) {
            $html .= '
          <option value="' . $row['pick_id'] . '">' . calculate_pick($row['pick_id']) . ' - ' . $row['team_name'] . ' - ' .
                $row['player_name'] . ' (' . $row['position_name'] . ')</option>';
        }
        $html .= '
        </select>
      </td>
    </tr>
  </table>
  <p align="center">
    <input type="submit" value="Roll Back" onclick="return confirm(\'Are you sure you want to roll back to this pick?\')">
</form>';

        return $html;
    }

    public function draw_chat()
    {
        global $login;
        if (!$login->team_id()) {
            header("Location: ./");
            exit;
        }
        $html = file_get_contents("includes/html/chat.html");
        $chat_id = md5(uniqid(rand()));
        $html = str_replace("%chat_id%", $chat_id, $html);
        if ($login->is_admin()) {
            $message = "<P>As the admin, you can see the entire transcript of the chat, including the time stamps for
each message.  Users will only see
the current chat while they are logged in.</p>";
        }
        // Get the chat up to now
        $statement = "select * from chat, team where team.team_id = chat.team_id and
chat_room_id is NULL";
        // Allow the admin to see the full chat transcript
        $chat_start = date("Y-m-d H:i:s", strtotime($_SESSION['chat_time'] . ' -2 hours'));
        if (!$login->is_admin()) {$statement .= " and chat_time > '$chat_start'";}
        $statement .= "
order by chat_time";
        $result = mysql_query($statement);
        $chat = '<span class="chat_text">';
        while ($row = mysql_fetch_array($result)) {
            if ($login->is_admin() || 1) {
                $chat .= '
<span class="chat_time">(' . date("m/d g:i:s a T", strtotime($row['chat_time'])) . ')</span><br>';
            }
            $chat .= '
<b>' . $row['team_name'] . ':</b> ' . htmlentities($row['chat_message']) . '<br>';
            $_SESSION['last_chat_id'] = $row['chat_id'];
            $_SESSION['last_printed_chat'][$chat_id] = $row['chat_id'];
        }
        $chat .= "</span>";
        $html = str_replace("%chat%", $chat, $html);
        $html = str_replace("%chat_room_id%", "", $html);

        $html = str_replace("%instructions%", $message, $html);
        return $html;
    }

    public function draw_show_player()
    {
        $player = new player($_GET['player_id']);
        return $player->draw();
    }

    public function draw_notes()
    {
        global $login;
        if (!$login->team_id()) {
            header("Location: ./");
            exit;
        }
        $html = file_get_contents("includes/html/notes.html");
        $html = str_replace("%team_comments%", $login->get_comments(), $html);
        return $html;
    }

    public function draw_link_teams()
    {
        global $login;
        if (!$login->is_admin()) {
            header("Location: ./");
            exit;
        }
        // Show all the teams and give the ability to link them to their corresponding names
        $statement = "select * from team_to_name order by team_name";
        $result = mysql_query($statement);
        $html .= '
<h3>Link Teams</h3>
<p>Use this table to link the team names to their 3-letter code.  Some of the values may be guessed;
please take the time to verify their accuracy.</p>
<form method="post" action="import_mock_draft_run.php">
<table class="data">
  <tr>
    <td class="heading">Team</td>
    <td class="heading">Code</td>
  </tr>';
        while ($row = mysql_fetch_array($result)) {
            $team = new team($row['team_id']);
            $html .= '
  <input type="hidden" name="team_name[' . $row['team_to_name_id'] . ']" value="' . $row['team_name'] . '">
  <tr>
    <td class="light">' . $row['team_name'] . '</td>
    <td class="light">
      <select name="team_id[' . $row['team_to_name_id'] . ']">
        <option value=""></option>';
            $html .= $team->option_list();
            $html .= '
      </select>
    </td>
  </tr>';
        }
        $html .= '
</table>
<p align="center">
<input type="submit" name="Assign and run" name="run">
</form>';
        return $html;
    }

    public function draw_scout_weights()
    {
        global $login;
        if (!$login->is_admin()) {
            header("Location: ./");
            exit;
        }
        $html .= '
<h3>Scout Weights</h3>
<p>To weight the positions of the scout picks and the mock draft, change the values here.  Larger values
will increase the likelihood of that position being chosen, smaller values will decrease it.  Keep in mind
that the in-game adjusted grade makes some allowances for this, so if you change these values you might want
to run a mock draft to see how it turns out.</p>
<p>If you already have a mock draft, changing these values will not change the existing mock draft, but will
affect future scout picks.</p>
<form method="post" action="scout_weights_run.php">
<div style="padding-left: 35%; padding-right: 35%;">
<table class="data">
  <tr>
    <td class="heading">Position</td>
    <td class="heading">Weight</td>
  </tr>';
        $statement = "select * from position order by position_id";
        $result = mysql_query($statement);
        while ($row = mysql_fetch_array($result)) {
            if ($class == 'dark') {
                $class = "light";
            } else {
                $class = "dark";
            }
            $html .= '
  <tr>
    <td class="' . $class . '">' . $row['position_name'] . '</td>
    <td class="' . $class . '">
      <input type="text" name="position_scout_weight[' . $row['position_id'] . ']" value="' . $row['position_scout_weight'] . '" size="4"> %
    </td>
  </tr>';
        }
        $html .= '
</table>
<p align="center">
<input type="submit" value="Save">
</div>';
        return $html;
    }

    public function draw_debug()
    {
        $html .= '
<form method="post" action="debug.php">
  <textarea name="query" cols="80" rows="5">' . $_POST['query'] . '</textarea>
  <input type="submit">
</form>';
        if ($_POST['query']) {
            $statement = $_POST['query'];
            $result = mysql_query($statement);
            $data = array();
            while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
                $header = array();
                foreach ($row as $key => $value) {
                    $header[] = $key;
                }
                $data[] = "<td>" . implode("</td><td>", $row) . "</td>";
            }
            $html .= '
<h2>' . $table . ' table:</h2>
<table border align="center">
  <tr>
    <th>' . implode("</th><th>", $header) . '</th>
  </tr>
  <tr>';
            $html .= implode("</tr><tr>", $data);
            $html .= '
  </tr>
</table>';
        } else {
            $tables = array("pick", "settings", "team");
            foreach ($tables as $table) {
                if ($table == 'settings') {
                    $statement = "select * from $table order by setting_id";
                } else {
                    $statement = "select * from $table order by " . $table . "_id";
                }
                $result = mysql_query($statement);
                $data = array();
                while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
                    $data[] = "<td>" . implode("</td><td>", $row) . "</td>";
                }
                $html .= '
<h2>' . $table . ' table:</h2>
<table border align="center">
  <tr>';
                $html .= implode("</tr><tr>", $data);
                $html .= '
  </tr>
</table>';
            }
        }
        if (!$_POST['query']) {
            phpinfo();
        }
        return $html;
    }
}
