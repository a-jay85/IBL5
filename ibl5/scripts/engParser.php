<?php

declare(strict_types=1);

/**
 * Energy File Parser Script
 *
 * Parses the .eng file from JSB simulation to read player energy values.
 */

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/autoloader.php';
include $_SERVER['DOCUMENT_ROOT'] . '/ibl5/config.php';
include $_SERVER['DOCUMENT_ROOT'] . '/ibl5/db/db.php';

use Scripts\MaintenanceRepository;

$repository = new MaintenanceRepository($mysqli_db);

$leagueFileName = $repository->getSetting('League File Name');
if ($leagueFileName === null) {
    die("Unable to find League File Name setting");
}

$engFilePath = $_SERVER['DOCUMENT_ROOT'] . "/ibl5/$leagueFileName.eng";
if (!file_exists($engFilePath)) {
    die("Energy file not found: $engFilePath");
}

$engFile = fopen($engFilePath, "rb");
$engArray = [];

while (!feof($engFile)) {
    $line = fgets($engFile);
    if ($line !== false && !preg_match('/^\s{3}/', $line)) {
        if (preg_match('/(.*), (.*)/', $line, $matches)) {
            $key = (string) $matches[1];
            $value = (int) $matches[2];
            $engArray[$key] = $value;
        }
    }
}

fclose($engFile);

if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'engParser.php') !== false) {
    var_dump($engArray);
}
