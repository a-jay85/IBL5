<?php

declare(strict_types=1);

/**
 * HeadToHeadRecords Module - Display head-to-head franchise / team / GM records matrix.
 *
 * @see \HeadToHeadRecords\HeadToHeadRecordsRepository For data access
 * @see \HeadToHeadRecords\HeadToHeadRecordsView       For HTML rendering
 * @see \HeadToHeadRecords\HeadToHeadRecordsController For filter logic
 */

if (!preg_match('/modules\.php/i', $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

global $mysqli_db, $user, $leagueContext;

$season      = new \Season\Season($mysqli_db, $leagueContext);
$logoResolver = new \HeadToHeadRecords\LogoResolver();
$imageRoot   = $_SERVER['DOCUMENT_ROOT'] . '/ibl5/images/logo';

$innerRepo = new \HeadToHeadRecords\HeadToHeadRecordsRepository(
    $mysqli_db,
    $season->endingYear,
    fn (int $id, string $name): string => $logoResolver->resolve($id, $name, $imageRoot),
);

$repo = new \HeadToHeadRecords\CachedHeadToHeadRecordsRepository(
    $innerRepo,
    new \Cache\DatabaseCache($mysqli_db)
);

$controller = new \HeadToHeadRecords\HeadToHeadRecordsController(
    $repo,
    new \HeadToHeadRecords\HeadToHeadRecordsView(),
    $season,
    $user ?? new stdClass(),
    $mysqli_db
);

\PageLayout\PageLayout::header();
$controller->main();
\PageLayout\PageLayout::footer();
