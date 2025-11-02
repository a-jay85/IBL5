<?php

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

global $db;

use Draft\DraftSelectionHandler;

$teamname = $_POST['teamname'];
$playerToBeDrafted = $_POST['player'];
$draft_round = (int)$_POST['draft_round'];
$draft_pick = (int)$_POST['draft_pick'];

$sharedFunctions = new Shared($db);
$season = new Season($db);

$handler = new DraftSelectionHandler($db, $sharedFunctions, $season);
echo $handler->handleDraftSelection($teamname, $playerToBeDrafted, $draft_round, $draft_pick);