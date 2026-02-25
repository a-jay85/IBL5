<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

// SECURITY: Admin-only endpoint
if (!function_exists('is_admin') || !is_admin($admin)) {
    header('Content-Type: application/json');
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}

header('Content-Type: application/json');

global $mysqli_db;

$repository = new Boxscore\BoxscoreRepository($mysqli_db);

$renameTeamId = isset($_POST['renameTeamId']) && is_string($_POST['renameTeamId'])
    ? (int) $_POST['renameTeamId']
    : 0;
$renameTeamName = isset($_POST['renameTeamName']) && is_string($_POST['renameTeamName'])
    ? trim($_POST['renameTeamName'])
    : '';

if ($renameTeamId <= 0 || $renameTeamName === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid team ID or name.']);
    exit;
}

// Enforce varchar(16) limit
$renameTeamName = mb_substr($renameTeamName, 0, 16);

$affectedRows = $repository->renameAllStarTeam($renameTeamId, $renameTeamName);

if ($affectedRows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'No record updated.']);
}
