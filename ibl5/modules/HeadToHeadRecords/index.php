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

global $user;
/** @var mixed $user */

$services = \Module\ModuleServices::current();
$factory = $services->factory(\Module\Factories\HeadToHeadRecordsFactory::class);
/** @var string $docRoot */
$docRoot   = $_SERVER['DOCUMENT_ROOT'];
$imageRoot = $docRoot . '/ibl5/images/logo';

// $user is the raw PHP-Nuke cookie string, not an object. Decode it to the
// username, then resolve that GM's teamid so the matrix can highlight their row.
// Anonymous visitors get no teamid and no highlight.
$nukeCompat  = $services->nukeCompat();
$gmTeamId = null;
if ($nukeCompat->isUser($user)) {
    $decoded  = $nukeCompat->cookieDecode($user);
    $username = is_string($decoded[1] ?? null) ? $decoded[1] : '';
    if ($username !== '') {
        $teamIdentity = $services->teamIdentity();
        $gmTeamName = $teamIdentity->getTeamnameFromUsername($username);
        $gmTeamId = $gmTeamName !== null ? $teamIdentity->getTidFromTeamname($gmTeamName) : null;
    }
}

$controller = $factory->controller($imageRoot)->withHighlightedTeam($gmTeamId);

\PageLayout\PageLayout::header();
$controller->main();
\PageLayout\PageLayout::footer();
