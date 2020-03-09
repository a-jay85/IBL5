<?
/***************************************************************************
 *                                options_set.php
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

include "includes/classes.inc.php";

if (preg_match("/[a-zA-Z0-9._%-]+@[a-zA-Z0-9._%-]+\.[a-zA-Z]{2,4}/", $_POST['team_email'])) {
  $col[] = "team_email = '".$_POST['team_email']."'";
  $has_email = true;
 } else {
  $col[] = "team_email = NULL";
  $has_email = false;
 }

if ($_POST['team_autopick']) {
  $col[] = "team_autopick = '1'";
 } else {
  $col[] = "team_autopick = '0'";
 }

$col[] = "team_email_prefs = '".$_POST['team_email_prefs']."'";

$col[] = "team_autopick_wait = '".$_POST['team_autopick_wait']."'";

if ($_POST['team_multipos']) {
  // This is stored kind of backwards
  $col[] = "team_multipos = '0'";
 } else {
  $col[] = "team_multipos = '1'";
 }

if ($_POST['pick_method_id']) {
  // Check to make sure that if we have a scout pick that we have a mock draft
  if ($_POST['pick_method_id'] == kScoutPick) {
    $statement = "select * from mock_draft";
    if (!mysql_num_rows(mysql_query($statement))) {
      $_SESSION['message'] = "Scout pick is not available for this draft.";
      $_POST['pick_method_id'] = kPlayerQueue;
    }
  }
  $col[] = "pick_method_id = '".$_POST['pick_method_id']."'";
 }

if ($login->is_admin() && !$has_email) {
  // Admin must have an e-mail address
  $_SESSION['message'] = "The admin account must have an e-mail address.";
 } else {
  $statement = "update team set ".implode(",",$col)." where team_id = '".$login->team_id()."'";
  mysql_query($statement);
  if (!$_SESSION['message']) {
    $_SESSION['message'] = "Options updated.";
  }
 }

// If admin, we might be changing the options
if ($login->is_admin()) {
  if ($_POST['pick_id']) {
    // We have a halt round
    $pick_id = $_POST['pick_id'];
    $statement = "update pick set player_id = '".kDraftHalt."', pick_time = NULL
where player_id is NULL and pick_id >= '$pick_id'";
    mysql_query($statement);
    $statement = "update pick set player_id = NULL, pick_time = NULL
where player_id = '".kDraftHalt."' and pick_id < '$pick_id'";
    mysql_query($statement);
    reset_current_pick_clock();
  } else {
    // No halt round, all player_id's need to be NULL
    $statement = "update pick set player_id = NULL, pick_time = NULL
where player_id = '".kDraftHalt."'";
    mysql_query($statement);
    reset_current_pick_clock();
  }
  // Set the league name
  if ($_POST['league_name']) {
    $settings->set_value(kSettingLeagueName, $_POST['league_name']);
  }

  // Send e-mails?
  if ($_POST['send_emails']) {
    $settings->set_value(kSettingSendMails, $_POST['send_emails']);
  } else {
    $settings->set_value(kSettingSendMails, 0);
  }

  if ($_POST['time_access'] > 0) {
    // Draft clock
    if ($_POST['start_hour'] > 0 && $_POST['end_hour'] > 0) {
      $start_time = strtotime($_POST['start_hour'].':'.sprintf("%02d", $_POST['start_min']).' '.$_POST['start_ampm']);
      $end_time = strtotime($_POST['end_hour'].':'.sprintf("%02d", $_POST['end_min']).' '.$_POST['end_ampm']);
      if ($start_time > $end_time) {
	$_SESSION['message'] = "The start time was later than the end time.";
      } else {
	$settings->set_value(kSettingStartTime, $start_time);
	$settings->set_value(kSettingEndTime, $end_time);
      }
    } else {
      $settings->set_value(kSettingStartTime, 0);
      $settings->set_value(kSettingEndTime, 0);      
    }
    
    // Set the pick time limit
    $hour = $_POST['pick_limit_hour'];
    $min = $_POST['pick_limit_min'];
    $time = $hour * 60 + $min;
    // How long is the draft clock running?  We can't have the pick time be longer than that.
    $window = $settings->get_value(kSettingEndTime) - $settings->get_value(kSettingStartTime);
    if ($window && ($time*60) > $window) {
      $_SESSION['message'] = "The pick time limit cannot be longer than the daily window";
      $time = floor($window/60);
    }
    $settings->set_value(kSettingPickTimeLimit, $time);
    if ($_POST['max_autopick_delay'] > $time) {
      $_POST['max_autopick_delay'] = $time;
    }
    // Make sure no teams have the option set longer than the new pick time limit
    if ($time) {
      $new_wait_limit = $time-5;
      if ($new_wait_limit < 0) {
	$new_wait_limit = 0;
      }
      $statement = "update team set team_autopick_wait = '$new_wait_limit' where team_autopick_wait >= '$time'";
      mysql_query($statement);
    }
    // Somehow teams are getting set to a negative time limit
    $statement = "update team set team_autopick_wait = '0' where team_autopick_wait < '0'";
    mysql_query($statement);
    // Time zone
    $settings->set_value(kSettingTimeZone, $_POST['time_zone']);
  }

  // set kSettingRolloverMethod
  $settings->set_value(kSettingRolloverMethod, $_POST['rollover_method']);
  // set kSettingAutopickReduction
  $settings->set_value(kSettingAutopickReduction, $_POST['autopick_reduction']);
  // What to do with expired picks
  $settings->set_value(kSettingExpiredPick, $_POST['team_expire']);
  // Max delay
  $settings->set_value(kSettingMaxDelay, $_POST['max_autopick_delay']);
  // Make sure none of the teams have a greater value
  if ($_POST['max_autopick_delay']) {
    $statement = "update team set team_autopick_wait = '".$_POST['max_autopick_delay']."' where
team_autopick_wait > '".$_POST['max_autopick_delay']."'";
    mysql_query($statement);
  }
 }

// In case we have updated the autopick setting, let's run the queue
process_pick_queue();

header("Location: options.php");
?>