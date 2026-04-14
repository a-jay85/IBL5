<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "Head-to-Head Records";

global $mysqli_db, $user;

$dbCache = new \Cache\DatabaseCache($mysqli_db);
$innerRepository = new \HeadToHeadRecords\HeadToHeadRecordsRepository($mysqli_db);
$repository = new \HeadToHeadRecords\CachedHeadToHeadRecordsRepository($innerRepository, $dbCache);
$view = new \HeadToHeadRecords\HeadToHeadRecordsView();
$commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
$season = new \Season\Season($mysqli_db);

$controller = new \HeadToHeadRecords\HeadToHeadRecordsController($repository, $view, $commonRepository);
$controller->main($user, $season);
