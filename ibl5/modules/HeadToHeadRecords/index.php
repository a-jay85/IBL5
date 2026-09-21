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
/** @var object|null $user */
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
