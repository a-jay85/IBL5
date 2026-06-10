<?php

declare(strict_types=1);

/**
 * Topics Module - Display all news topics with recent articles
 *
 * Shows a grid of all active news topics with their images, article counts,
 * total reads, and recent article listings.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see Topics\TopicsRepository For data retrieval
 * @see Topics\TopicsView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use Topics\TopicsService;
use Topics\TopicsView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- " . _ACTIVETOPICS;

global $mysqli_db, $prefix, $user_prefix, $tipath, $articlecomm;

$ThemeSel = 'IBL';

// Determine the image path: use theme-specific images if available, fall back to default
$themePath = (is_dir("themes/{$ThemeSel}/images/topics/"))
    ? "themes/{$ThemeSel}/images/topics/"
    : (string) $tipath;

// Initialize service (owns the Topics + Search repositories)
$service = new TopicsService($mysqli_db, $prefix);
$view = new TopicsView();

// Assemble page data (topics + search filters)
$pageData = $service->getPageData((bool) ($articlecomm ?? false));
$topics = $pageData['topics'];
$searchFilters = $pageData['searchFilters'];

// Render page
PageLayout\PageLayout::header();
echo $view->render($topics, $themePath, $searchFilters);
PageLayout\PageLayout::footer();
