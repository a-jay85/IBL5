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
    $stmt = $username !== ''
        ? $services->db()->prepare('SELECT teamid FROM `ibl_team_info` WHERE gm_username = ? LIMIT 1')
        : false;
    if ($stmt !== false) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($fetchedTeamId);
        $fetched = $stmt->fetch();
        $stmt->close();
        if ($fetched === true && is_numeric($fetchedTeamId)) {
            $gmTeamId = (int) $fetchedTeamId;
        }
    }
}

$controller = $factory->controller($imageRoot)->withHighlightedTeam($gmTeamId);

\PageLayout\PageLayout::header();
$controller->main();
\PageLayout\PageLayout::footer();
