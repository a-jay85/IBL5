<?php

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

global $mysqli_db;

// Get POST parameters
$teamName = $_POST['teamname'] ?? '';
$playerID = isset($_POST['playerID']) ? (int) $_POST['playerID'] : 0;
$extensionAmount = isset($_POST['rookieOptionValue']) ? (int) $_POST['rookieOptionValue'] : 0;

// Validate input
if (empty($teamName) || $playerID === 0 || $extensionAmount === 0) {
    die("Invalid request. Missing required parameters.");
}

// Process rookie option using controller
$controller = new RookieOption\RookieOptionController($mysqli_db);
$controller->processRookieOption($teamName, $playerID, $extensionAmount);
