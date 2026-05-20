<?php

declare(strict_types=1);

/**
 * Search Module - Search stories, comments, and users
 *
 * Provides a search interface with filtering by topic, category, author,
 * and date range. Supports searching across stories, comments, and users.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see Search\SearchRepository For data retrieval
 * @see Search\SearchView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use Search\SearchRepository;
use Search\SearchView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$query    = is_string($_REQUEST['query']    ?? null) ? stripslashes(check_html($_REQUEST['query'], 'nohtml')) : '';
$type     = is_string($_REQUEST['type']     ?? null) ? $_REQUEST['type']     : '';
$topic    = is_numeric($_REQUEST['topic']    ?? null) ? (int) $_REQUEST['topic']    : 0;
$category = is_numeric($_REQUEST['category'] ?? null) ? (int) $_REQUEST['category'] : 0;
$author   = is_string($_REQUEST['author']   ?? null) ? $_REQUEST['author']   : '';
$days     = is_numeric($_REQUEST['days']     ?? null) ? (int) $_REQUEST['days']     : 0;
$min      = is_numeric($_REQUEST['min']      ?? null) ? (int) $_REQUEST['min']      : 0;
$qlen     = is_numeric($_REQUEST['qlen']     ?? null) ? (int) $_REQUEST['qlen']     : 0;

$offset = 10;
$max = $min + $offset;

global $prefix, $user_prefix, $mysqli_db, $module_name, $articlecomm;

// Redirect if query is too short
if ($query !== '' && strlen($query) < 3) {
    \Utilities\HtmxHelper::redirect("modules.php?name={$module_name}&qlen=1");
}

$pagetitle = "- " . _SEARCH;

// Initialize services
$service = new SearchRepository($mysqli_db, $prefix);
$view = new SearchView();

// Get topic context for header display
$topicText = _ALLTOPICS;
if ($topic > 0) {
    $topicInfo = $service->getTopicInfo($topic);
    if ($topicInfo !== null) {
        $topicText = $topicInfo['topicText'];
    }
}

// Get filter options
$topics = $service->getTopics();
$categories = $service->getCategories();
$authors = $service->getAuthors();

// Execute search if query is provided
$results = null;
$hasMore = false;
$error = '';

if ($qlen === 1) {
    $error = 'Your query should be at least 3 characters long.';
}

if ($query !== '' && strlen($query) >= 3) {
    if ($type === 'comments') {
        $searchResult = $service->searchComments($query, $min, $offset);
    } elseif ($type === 'users') {
        $searchResult = $service->searchUsers($query, $min, $offset);
    } else {
        $searchResult = $service->searchStories($query, $topic, $category, $author, $days, $min, $offset);
    }

    $results = $searchResult['results'];
    $hasMore = $searchResult['hasMore'];
}

// Build view data
$data = [
    'query' => $query,
    'type' => $type,
    'topic' => $topic,
    'category' => $category,
    'author' => $author,
    'days' => $days,
    'min' => $min,
    'offset' => $offset,
    'topicText' => $topicText,
    'topics' => $topics,
    'categories' => $categories,
    'authors' => $authors,
    'results' => $results,
    'hasMore' => $hasMore,
    'articleComm' => (bool) ($articlecomm ?? false),
    'error' => $error,
];

// Render page
PageLayout\PageLayout::header();
echo $view->render($data);
PageLayout\PageLayout::footer();
