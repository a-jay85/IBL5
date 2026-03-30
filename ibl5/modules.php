<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2007 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

define('MODULE_FILE', true);
require_once __DIR__ . '/mainfile.php';

if (isset($name) && $name == $_REQUEST['name']) {
    $name = trim($name);

    // SECURITY: Validate module name - must be alphanumeric with underscores only
    // Also use basename() to strip any path components
    $name = basename($name);
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        header("Location: index.php");
        exit;
    }

    // Legacy check for path traversal (kept for defense in depth)
    if (str_contains($name, "..")) {
        header("Location: index.php");
        exit;
    }

    // Full-page cache for anonymous GET requests.
    // Placed before ModuleAccessControl to skip DB queries entirely on cache hits.
    // Auth check reads only $_SESSION — zero DB cost.
    global $authService;
    $pageCacheKey = null;
    $hasFlash = isset($_SESSION['flash_success'])
        && is_string($_SESSION['flash_success'])
        && $_SESSION['flash_success'] !== '';

    if (
        $_SERVER['REQUEST_METHOD'] === 'GET'
        && !$authService->isAuthenticated()
        && !$hasFlash
        && \Cache\PageCache::isCacheable($name)
    ) {
        $isBoosted = \Utilities\HtmxHelper::isBoostedRequest();
        $rawUri = $_SERVER['REQUEST_URI'] ?? '';
        $pageCacheKey = \Cache\PageCache::buildCacheKey(
            is_string($rawUri) ? $rawUri : '',
            $isBoosted
        );
        $cached = \Cache\PageCache::get($pageCacheKey);

        if ($cached !== null) {
            header('Content-Type: text/html; charset=utf-8');
            header('X-IBL-Cache: HIT');
            echo $cached;
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            exit;
        }
    }

    // Phase-based access control
    global $mysqli_db, $leagueContext;
    $season = new \Season\Season($mysqli_db);
    $accessControl = new Module\ModuleAccessControl($season, $leagueContext, $mysqli_db);

    $isModuleAccessible = $accessControl->isModuleAccessible($name);
    if (!$isModuleAccessible && !is_admin()) {
        PageLayout\PageLayout::header();
        OpenTable();
        echo "<center>" . _MODULENOTACTIVE . "<br><br>" . _GOBACK . "</center>";
        CloseTable();
        PageLayout\PageLayout::footer();
    } else {
        if (!$isModuleAccessible) {
            define('ADMIN_PHASE_GATE_NOTICE', true);
        }
        if (!isset($file) or $file != $_REQUEST['file']) {
            $file = "index";
        }

        // SECURITY: Validate file name - must be alphanumeric with underscores only
        // Also use basename() to strip any path components
        $file = basename($file);
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $file)) {
            die("Invalid file name");
        }

        // Legacy check for path traversal (kept for defense in depth)
        if (str_contains($file, "..")) {
            die("You are so cool...");
        }

        $ThemeSel = 'IBL';
        if (file_exists("themes/$ThemeSel/modules/$name/" . $file . ".php")) {
            $modpath = "themes/$ThemeSel/";
        } else {
            $modpath = "";
        }

        $modpath .= "modules/$name/" . $file . ".php";
        if (file_exists($modpath)) {
            // On cache miss, capture output via ob callback for next request
            if ($pageCacheKey !== null) {
                $captureKey = $pageCacheKey;
                $captureTtl = \Cache\PageCache::getTtl($name);
                ob_start(static function (string $html) use ($captureKey, $captureTtl): string {
                    \Cache\PageCache::set($captureKey, $html, $captureTtl);
                    return $html;
                });
            }
            include $modpath;
        } else {
            PageLayout\PageLayout::header();
            OpenTable();
            echo "<br><center>Sorry, such file doesn't exist...</center><br>";
            CloseTable();
            PageLayout\PageLayout::footer();
        }
    }
} else {
    header("Location: index.php");
    exit;
}
