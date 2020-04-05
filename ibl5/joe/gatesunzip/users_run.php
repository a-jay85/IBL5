<?
/***************************************************************************
 *                                users_run.php
 *                            -------------------
 *   begin                : Monday, Mar 31, 2008
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

include "includes/classes.inc.php";

// Admin only!
if ($login->is_admin()) {
  if (is_array($_POST['team_id'])) {
    foreach($_POST['team_id'] as $team_id) {
      $team = new team($team_id);
      if ($_POST['has_password'][$team_id]) {
	$team->set_has_password();
      } else {
	$team->clear_password();
      }
      $team->set_autopick($_POST['team_autopick'][$team_id]);
      $team->set_clock_adj($_POST['team_clock_adj'][$team_id]);
    }
  }
 }

// May have updated the pick list by turning autopick on
process_pick_queue();

header("Location: users.php");
?>