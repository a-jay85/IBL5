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

global $user;
/** @var mixed $user */

$docRoot = is_string($_SERVER['DOCUMENT_ROOT'] ?? null) ? $_SERVER['DOCUMENT_ROOT'] : '';

// $user is the raw PHP-Nuke cookie string, not an object. Decode it to the
// username, then resolve that GM's teamid so the tables can highlight their row.
// Anonymous visitors get no teamid and no highlight.
$teamIdentity = \Module\ModuleServices::current()->teamIdentity();
$nukeCompat   = \Module\ModuleServices::current()->nukeCompat();
$userTeamId   = null;
if ($nukeCompat->isUser($user)) {
    $decoded    = $nukeCompat->cookieDecode($user);
    $username   = is_string($decoded[1] ?? null) ? $decoded[1] : '';
    $gmTeamName = $username !== '' ? $teamIdentity->getTeamnameFromUsername($username) : null;
    $userTeamId = $gmTeamName !== null ? $teamIdentity->getTidFromTeamname($gmTeamName) : null;
}

$userTeamKey = $userTeamId !== null ? (string) $userTeamId : null;
$owner  = $userTeamId !== null ? $teamIdentity->getOwnerName($userTeamId) : null;

$factory       = \Module\ModuleServices::current()->factory(\Module\Factories\RivalriesFactory::class);
$rivalriesRepo = $factory->repository();

// The view compares GM names with ===, so use the spelling stored in ibl_gm_tenures.
$userGm = is_string($owner) && $owner !== '' ? $rivalriesRepo->findGmName($owner) : null;

$controller = $factory->controller($docRoot . '/ibl5/images/logo');

\PageLayout\PageLayout::header();
/** @var array<string, mixed> $get */
$get = $_GET;
$controller->main($get, $userTeamKey, $userGm);
\PageLayout\PageLayout::footer();
