<?php

declare(strict_types=1);

/**
 * HeadToHeadRecords Module - Display head-to-head franchise / team / GM records matrix.
 *
 * @see \HeadToHeadRecords\HeadToHeadRecordsRepository For data access
 * @see \HeadToHeadRecords\HeadToHeadRecordsView       For HTML rendering
 * @see \HeadToHeadRecords\HeadToHeadRecordsController For filter logic
 */

$phpSelf = is_string($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
if (preg_match('/modules\.php/i', $phpSelf) === 0) {
    die("You can't access this file directly...");
}

global $mysqli_db, $user, $leagueContext;
/** @var \mysqli $mysqli_db */
/** @var mixed $user */
/** @var \League\LeagueContext|null $leagueContext */

$season      = new \Season\Season($mysqli_db, $leagueContext);
$logoResolver = new \HeadToHeadRecords\LogoResolver();
/** @var string $docRoot */
$docRoot   = $_SERVER['DOCUMENT_ROOT'];
$imageRoot = $docRoot . '/ibl5/images/logo';

$innerRepo = new \HeadToHeadRecords\HeadToHeadRecordsRepository(
    $mysqli_db,
    $season->endingYear,
    fn (int $id, string $name): string => $logoResolver->resolve($id, $name, $imageRoot),
);

$repo = new \HeadToHeadRecords\CachedHeadToHeadRecordsRepository(
    $innerRepo,
    new \Cache\DatabaseCache($mysqli_db)
);

// $user is the raw PHP-Nuke cookie string, not an object. Decode it to the
// username, then resolve that GM's teamid so the matrix can highlight their row.
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

$controller = new \HeadToHeadRecords\HeadToHeadRecordsController(
    $repo,
    new \HeadToHeadRecords\HeadToHeadRecordsView(),
    $season,
    $currentUser,
    $mysqli_db
);

\PageLayout\PageLayout::header();
$controller->main();
\PageLayout\PageLayout::footer();
