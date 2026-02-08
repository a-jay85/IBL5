<?php

/**
 * XSS Vulnerability Patterns - Before and After
 *
 * This file demonstrates common XSS vulnerabilities and their fixes.
 */

// ============================================
// EXAMPLE 1: Direct Output of Database Content
// ============================================

// ❌ VULNERABLE - Direct output
function renderPlayerNameVulnerable(array $player): string
{
    return "<td>" . $player['name'] . "</td>";
}

// ✅ SECURE - Using HtmlSanitizer
function renderPlayerNameSecure(array $player): string
{
    return "<td>" . \Utilities\HtmlSanitizer::safeHtmlOutput($player['name']) . "</td>";
}


// ============================================
// EXAMPLE 2: Short Echo Tags in Templates
// ============================================

// ❌ VULNERABLE
// <?= $row['team_name'] ?>
// <?= $errorMessage ?>

// ✅ SECURE
// <?= \Utilities\HtmlSanitizer::safeHtmlOutput($row['team_name']) ?>
// <?= \Utilities\HtmlSanitizer::safeHtmlOutput($errorMessage) ?>


// ============================================
// EXAMPLE 3: User Input in Error Messages
// ============================================

// ❌ VULNERABLE
function showErrorVulnerable(string $search): string
{
    return "No results found for: " . $search;
}

// ✅ SECURE
function showErrorSecure(string $search): string
{
    return "No results found for: " . \Utilities\HtmlSanitizer::safeHtmlOutput($search);
}


// ============================================
// EXAMPLE 4: Play-by-Play Text
// ============================================

// ❌ VULNERABLE - Game text from database
function renderPlayByPlayVulnerable(array $play): string
{
    return "<p>{$play['description']}</p>";
}

// ✅ SECURE
function renderPlayByPlaySecure(array $play): string
{
    return "<p>" . \Utilities\HtmlSanitizer::safeHtmlOutput($play['description']) . "</p>";
}


// ============================================
// EXAMPLE 5: HTML Attributes
// ============================================

// ❌ VULNERABLE - Player name in attribute
function renderPlayerLinkVulnerable(array $player): string
{
    return '<a href="player.php?id=' . $player['id'] . '" title="' . $player['name'] . '">View</a>';
}

// ✅ SECURE
function renderPlayerLinkSecure(array $player): string
{
    $id = (int) $player['id'];
    $name = \Utilities\HtmlSanitizer::safeHtmlOutput($player['name']);
    return '<a href="player.php?id=' . $id . '" title="' . $name . '">View</a>';
}


// ============================================
// EXAMPLE 6: JavaScript Context
// ============================================

// ❌ VULNERABLE
function renderJsDataVulnerable(array $data): string
{
    return '<script>var playerName = "' . $data['name'] . '";</script>';
}

// ✅ SECURE - Use json_encode for JS context
function renderJsDataSecure(array $data): string
{
    return '<script>var playerName = ' . json_encode($data['name']) . ';</script>';
}
