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

$season  = \Season\SharedSeason::get($mysqli_db, $leagueContext);
$docRoot = is_string($_SERVER['DOCUMENT_ROOT'] ?? null) ? $_SERVER['DOCUMENT_ROOT'] : '';

// $user is the raw PHP-Nuke cookie string, not an object. Decode it to the
// username, then resolve that GM's teamid so the tables can highlight their row.
// Anonymous visitors get no teamid and no highlight.
$teamIdentity = new \Repositories\TeamIdentityRepository($mysqli_db);
$nukeCompat   = new \Utilities\NukeCompat();
$userTeamId   = null;
if ($nukeCompat->isUser($user)) {
    $decoded    = $nukeCompat->cookieDecode($user);
    $username   = is_string($decoded[1] ?? null) ? $decoded[1] : '';
    $userTeamId = $username !== '' ? $teamIdentity->getTidFromUsername($username) : null;
}

$userTeamKey = $userTeamId !== null ? (string) $userTeamId : null;
$owner  = $userTeamId !== null ? $teamIdentity->getOwnerName($userTeamId) : null;

$rivalriesRepo = \Rivalries\RivalriesServiceFactory::createRepository($mysqli_db);

// The view compares GM names with ===, so use the spelling stored in ibl_gm_tenures.
$userGm = is_string($owner) && $owner !== '' ? $rivalriesRepo->findGmName($owner) : null;

$controller = new \Rivalries\RivalriesController(
    \Rivalries\RivalriesServiceFactory::create($mysqli_db, $season, $docRoot . '/ibl5/images/logo'),
    new \Rivalries\RivalriesView(),
    $rivalriesRepo,
);

\PageLayout\PageLayout::header();
/** @var array<string, mixed> $get */
$get = $_GET;
$controller->main($get, $userTeamKey, $userGm);
\PageLayout\PageLayout::footer();
