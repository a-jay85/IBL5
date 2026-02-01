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
 * @see Search\SearchService For data retrieval
 * @see Search\SearchView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use Search\SearchService;
use Search\SearchView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

// Collect and sanitize parameters
$query = isset($query) ? stripslashes(check_html((string) $query, "nohtml")) : '';
$type = isset($type) ? (string) $type : '';
$topic = isset($topic) ? intval($topic) : 0;
$category = isset($category) ? intval($category) : 0;
$author = isset($author) ? (string) $author : '';
$days = isset($days) ? intval($days) : 0;
$min = isset($min) ? intval($min) : 0;
$qlen = isset($qlen) ? intval($qlen) : 0;

$offset = 10;
$max = $min + $offset;

global $admin, $prefix, $user_prefix, $mysqli_db, $module_name, $articlecomm, $admin_file;

// Redirect if query is too short
if ($query !== '' && strlen($query) < 3) {
    header("Location: modules.php?name={$module_name}&qlen=1");
    exit;
}

$pagetitle = "- " . _SEARCH;

// Initialize services
$service = new SearchService($mysqli_db, $prefix, $user_prefix);
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
    'isAdmin' => (bool) is_admin($admin),
    'adminFile' => (string) $admin_file,
    'articleComm' => (bool) ($articlecomm ?? false),
    'error' => $error,
];

// Render page
Nuke\Header::header();
echo $view->render($data);
Nuke\Footer::footer();
