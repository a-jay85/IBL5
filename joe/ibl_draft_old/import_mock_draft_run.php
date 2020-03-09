<?
/***************************************************************************
 *                                import_mock_draft_run.php
 *                            --------------------------------
 *   begin                : Friday, Aug 22, 2008
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
include "includes/extractor_columns.inc.php";

// This function imports the mock draft file, or processes it.
if (!$login->is_admin()) {
  header("Location: ./");
  exit;
}

// Make sure we have adjusted grades for the players
$statement = "select * from player where player_adj_score is not NULL";
if (!mysql_num_rows(mysql_query($statement))) {
  $_SESSION['message'] = "No adjusted scores have been uploaded for this draft, mock draft is not available.";
  header("Location: ./");
  exit;
}

// First see if we are uploading the file
if ($_POST['upload']) {
  if (file_exists($_FILES['mock']['tmp_name'])) {
    // Store the file contents in the session, we'll need it later
    $_SESSION['mock_upload'] = preg_split("/[\n\r]+/", file_get_contents($_FILES['mock']['tmp_name']));
    // Now we just need to loop through all the teams and make sure they exist in the team_to_name table
    $lines = $_SESSION['mock_upload'];
    $header = true;
    $teams = array();
    $statement = "select * from team_to_name";
    $result = mysql_query($statement);
    while ($row = mysql_fetch_array($result)) {
      $teams[] = addslashes($row['team_name']);
    }
    foreach($lines as $line) {
      if ($header) {
	// Make sure we have the current version of Extractor coming in.
	if ($line != $valid_extractor) {
	  $_SESSION['message'] = "The file you imported does not appear to be an Extractor file, or is the wrong version.
Please verify that you are uploading the correct file and that you have the current version of Extractor.";
	  header("Location: import_mock_draft.php");
	  exit;
	}
      }
      if ($line && !$header) {
	preg_match_all('/("(?:[^"]|"")*"|[^",\r\n]*)(,|\r\n?|\n)?/', $line, $matches);
	$columns = $matches[0];
	foreach($columns as $key=>$value) {
	  // Remove the field qualifiers, if any
	  $columns[$key] = preg_replace("/^\"|\"$|\"?,$/", "", $value);
	}
	$team_name = addslashes($columns[kTeam]);
	if (!in_array($team_name, $teams)) {
	  // See if we can guess the team
	  $statement = "select * from team where team_name like '".substr($team_name, 0, 3)."'";
	  $row = mysql_fetch_array(mysql_query($statement));
	  if ($row['team_id']) {
	    $team_id = "'".$row['team_id']."'";
	  } else {
	    $team_id = "NULL";
	  }
	  $statement = "insert into team_to_name (team_name, team_id) values ('$team_name', $team_id)";
	  mysql_query($statement);
	  $teams[] = $team_name;
	}
      } else {
	$header = false;
      }
    }
    header("Location: link_teams.php");
    exit;
  } else {
    $_SESSION['message'] = "There was an error uploading the extractor file.";
  }
} else {
  // Running the mock draft.  First update the team_names table.
  if (is_array($_POST['team_id'])) {
    foreach($_POST['team_id'] as $team_to_name_id=>$team_id) {
      if ($team_id) {
	$statement = "update team_to_name set team_id = '$team_id' where team_to_name_id = '$team_to_name_id'";
	$team_ids[$_POST['team_name'][$team_to_name_id]] = $team_id;
      } else {
	$statement = "update team_to_name set team_id = NULL where team_to_name_id = '$team_to_name_id'";
      }
      mysql_query($statement);
    }
  }
  // Now we can run through all the lines in the upload
  $lines = $_SESSION['mock_upload'];
  $header = true;
  $data = array();
  foreach($lines as $line) {
    if ($header) {
      // Make sure we have the current version of Extractor coming in.
      if ($line != $valid_extractor) {
	$_SESSION['message'] = "The file you imported does not appeart to be an Extractor file, or is the wrong version.
Please verify that you are uploading the correct file and that you have the current version of Extractor.";
	header("Location: import_mock_draft.php");
	exit;
      }
    }
    if ($line && !$header) {
      preg_match_all('/("(?:[^"]|"")*"|[^",\r\n]*)(,|\r\n?|\n)?/', $line, $matches);
      $columns = $matches[0];
      foreach($columns as $key=>$value) {
	// Remove the field qualifiers, if any
	$columns[$key] = preg_replace("/^\"|\"$|\"?,$/", "", $value);
      }
      // Store the best player at each position for each team.
      $position_id = $positions[$columns[kPosition]];
      $future = $columns[kFuture];
      $team = $team_ids[addslashes($columns[kTeam])];
      if ($data[$team][$position_id] < $future) {
	$data[$team][$position_id] = $future;
      }
    }
    $header = false;
  }
  // Ok, $data contains all the data we need to fill the team_need table.
  // Truncate the team_need table
  $statement = "truncate table team_need";
  mysql_query($statement);
  // First let's build an array of all the position_id's
  $statement = "select * from position";
  $result = mysql_query($statement);
  $position_list = array();
  while ($row = mysql_fetch_array($result)) {
    $position_list[] = $row['position_id'];
  }
  foreach($data as $team_id=>$team_data) {
    foreach($position_list as $position_id) {
      // The need_order is 100-the best player
      $need = 100-$team_data[$position_id];
      $statement = "insert into team_need (team_id, position_id, team_need_order)
values ('$team_id', '$position_id', '$need')";
      mysql_query($statement);
    }
  }
  
  // Now we go through all the picks and do the mock draft
  $statement = "truncate table mock_draft";
  mysql_query($statement);
  $statement = "select * from pick where team_id > 0 order by pick_id";
  $result = mysql_query($statement);
  while($row = mysql_fetch_array($result)) {
    $team = new team($row['team_id']);
    $team->mock_pick($row['pick_id']);
  }

  // Lastly mark the any picks that have already run
  fill_team_need();
}
header("Location: mock_draft.php");
?>