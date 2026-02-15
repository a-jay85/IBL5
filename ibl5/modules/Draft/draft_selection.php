<?php

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

global $mysqli_db;

use Draft\DraftSelectionHandler;

$teamname = $_POST['teamname'];
$playerToBeDrafted = $_POST['player'];
$draft_round = (int)$_POST['draft_round'];
$draft_pick = (int)$_POST['draft_pick'];

$sharedRepository = new Shared\SharedRepository($mysqli_db);
$season = new Season($mysqli_db);

$handler = new DraftSelectionHandler($mysqli_db, $sharedRepository, $season);
echo $handler->handleDraftSelection($teamname, $playerToBeDrafted, $draft_round, $draft_pick);