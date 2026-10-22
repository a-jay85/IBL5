<?php

declare(strict_types=1);

/**
 * Rivalries Module - Ranked franchise and GM rivalries, pair pages, GM rival lists.
 *
 * @see \Rivalries\RivalriesRepository For data access
 * @see \Rivalries\RivalriesService    For ranking and pair assembly
 * @see \Rivalries\RivalriesView       For HTML rendering
 * @see \Rivalries\RivalriesController For GET validation and routing
 */

$phpSelf = is_string($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
if (preg_match('/modules\.php/i', $phpSelf) === 0) {
    die("You can't access this file directly...");
}

global $mysqli_db, $user, $leagueContext;
/** @var \mysqli $mysqli_db */
/** @var mixed $user */
/** @var \League\LeagueContext|null $leagueContext */

// Rivalries reads IBL tables only, so Olympics gets an empty state instead of IBL data.
if ($leagueContext !== null && $leagueContext->isOlympics()) {
    \PageLayout\PageLayout::header();
    echo '<p>Rivalries are not available for the Olympics league.</p>';
    \PageLayout\PageLayout::footer();
    return;
}

$season  = new \Season\Season($mysqli_db, $leagueContext);
$docRoot = is_string($_SERVER['DOCUMENT_ROOT'] ?? null) ? $_SERVER['DOCUMENT_ROOT'] : '';

// $user is the raw PHP-Nuke cookie string, not an object. Decode it to the
// username, then resolve that GM's teamid so the tables can highlight their row.
// Anonymous visitors get a bare object with no teamid and no highlight.
$nukeCompat  = new \Utilities\NukeCompat();
$currentUser = new stdClass();
if ($nukeCompat->isUser($user)) {
    $decoded  = $nukeCompat->cookieDecode($user);
    $username = is_string($decoded[1] ?? null) ? $decoded[1] : '';
    $stmt = $username !== ''
        ? $mysqli_db->prepare('SELECT teamid FROM `ibl_team_info` WHERE gm_username = ? LIMIT 1')
        : false;
    if ($stmt !== false) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($gmTeamId);
        $fetched = $stmt->fetch();
        $stmt->close();
        if ($fetched === true && is_numeric($gmTeamId)) {
            $currentUser->teamid = (int) $gmTeamId;
        }
    }
}

$userTeamId  = isset($currentUser->teamid) && is_int($currentUser->teamid) ? $currentUser->teamid : null;
$userTeamKey = $userTeamId !== null ? (string) $userTeamId : null;
$owner  = $userTeamId !== null
    ? (new \Repositories\TeamIdentityRepository($mysqli_db))->getOwnerName($userTeamId)
    : null;
$userGm = is_string($owner) && $owner !== '' ? $owner : null;

$controller = new \Rivalries\RivalriesController(
    \Rivalries\RivalriesServiceFactory::create($mysqli_db, $season, $docRoot . '/ibl5/images/logo'),
    new \Rivalries\RivalriesView(),
    \Rivalries\RivalriesServiceFactory::createRepository($mysqli_db),
);

\PageLayout\PageLayout::header();
/** @var array<string, mixed> $get */
$get = $_GET;
$controller->main($get, $userTeamKey, $userGm);
\PageLayout\PageLayout::footer();
