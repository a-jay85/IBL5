<?php

declare(strict_types=1);

require __DIR__ . '/../../mainfile.php';

global $mysqli_db;

if (!\Utilities\CsrfGuard::validateSubmittedToken('extension')) {
    header('Location: /ibl5/index.php');
    exit;
}

// Collect input data
$teamName = is_string($_POST['teamName'] ?? null) ? $_POST['teamName'] : '';
$playerID = (int) ($_POST['playerID'] ?? 0);
$playerName = is_string($_POST['playerName'] ?? null) ? $_POST['playerName'] : '';
$demandsYears = $_POST['demandsYears'] ?? '';
$demandsTotal = $_POST['demandsTotal'] ?? '';

// Build offer array
$offer = [
    'year1' => (int) ($_POST['offerYear1'] ?? 0),
    'year2' => (int) ($_POST['offerYear2'] ?? 0),
    'year3' => (int) ($_POST['offerYear3'] ?? 0),
    'year4' => (int) ($_POST['offerYear4'] ?? 0),
    'year5' => (int) ($_POST['offerYear5'] ?? 0),
];

// Build demands array
$demands = [
    'total' => $demandsTotal,
    'years' => $demandsYears,
];

// Build extension data for processor
$extensionData = [
    'teamName' => $teamName,
    'playerID' => $playerID,
    'playerName' => $playerName,
    'offer' => $offer,
    'demands' => $demands,
];

// Process extension using new architecture
$processor = new \Extension\ExtensionProcessor($mysqli_db);
$result = $processor->processExtension($extensionData);

// Look up team ID for redirect
$commonRepo = new \Services\CommonMysqliRepository($mysqli_db);
$tid = $commonRepo->getTidFromTeamname($teamName);

if ($tid === null) {
    header('Location: /ibl5/index.php');
    exit;
}

$redirectBase = '/ibl5/modules.php?name=Team&op=team&teamID=' . $tid . '&display=contracts';

if (!$result['success']) {
    $redirectUrl = $redirectBase . '&result=extension_error&msg=' . rawurlencode($result['error']);
} elseif ($result['accepted']) {
    $redirectUrl = $redirectBase . '&result=extension_accepted&msg=' . rawurlencode($result['message']);
} else {
    $redirectUrl = $redirectBase . '&result=extension_rejected&msg=' . rawurlencode($result['message']);
}

header('Location: ' . $redirectUrl);
exit;
