<?php

declare(strict_types=1);

require __DIR__ . '/../../mainfile.php';

global $mysqli_db, $cookie, $user;

if (!\Security\CsrfGuard::validateSubmittedToken('extension')) {
    \Utilities\HtmxHelper::redirect('/ibl5/index.php');
}

// Auth + ownership gate (IDOR fix D-10). is_user()/cookiedecode() ignore their
// argument and read the $authService global that mainfile.php's boot() wired up.
if (!is_user($user ?? '')) {
    \Utilities\HtmxHelper::redirect('/ibl5/index.php');
}

cookiedecode($user ?? '');
$username = is_string($cookie[1] ?? null) ? $cookie[1] : '';
$commonRepo = new \Repositories\TeamIdentityRepository($mysqli_db);
$sessionTeam = $commonRepo->getTeamnameFromUsername($username);
if ($sessionTeam === null || $sessionTeam === '' || $sessionTeam === \League\League::FREE_AGENTS_TEAM_NAME) {
    \Utilities\HtmxHelper::redirect('/ibl5/index.php');
}

// Ownership check: the POSTed team must equal the session team. A logged-in GM
// cannot replay a valid CSRF token against another team. The distinct signal
// makes the IDOR rejection provable (not the generic not-found bounce).
$postedTeam = is_string($_POST['teamName'] ?? null) ? $_POST['teamName'] : '';
if ($postedTeam !== $sessionTeam) {
    \Utilities\HtmxHelper::redirect('/ibl5/index.php?result=extension_forbidden');
}

// Use the session team as the authoritative value downstream — never POST input.
$teamName = $sessionTeam;
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
$processor = new \Extension\ExtensionProcessor($mysqli_db, $_SERVER['SERVER_NAME'] ?? '');
$result = $processor->processExtension($extensionData);

// Look up team ID for redirect ($commonRepo built above during the ownership gate)
$teamid = $commonRepo->getTidFromTeamname($teamName);

if ($teamid === null) {
    \Utilities\HtmxHelper::redirect('/ibl5/index.php');
}

$redirectBase = '/ibl5/modules.php?name=Team&op=team&teamid=' . $teamid . '&display=contracts';

if (!$result['success']) {
    $redirectUrl = $redirectBase . '&result=extension_error&msg=' . rawurlencode($result['error']);
} elseif ($result['accepted']) {
    $redirectUrl = $redirectBase . '&result=extension_accepted&msg=' . rawurlencode($result['message']);
} else {
    $redirectUrl = $redirectBase . '&result=extension_rejected&msg=' . rawurlencode($result['message']);
}

\Utilities\HtmxHelper::redirect($redirectUrl);
