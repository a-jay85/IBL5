<?php

declare(strict_types=1);

/**
 * Shared CLI bootstrap for the Discord bug-pipeline command wrappers.
 *
 * `require`d by every ibl5/scripts/bug-pipeline/*.php command. Copied from
 * scripts/runEngineShadow.php:34-61 (the repo's CLI-bootstrap idiom), adjusted
 * for the extra directory depth: these scripts sit in scripts/bug-pipeline/, so
 * every `__DIR__ . '/../'` there becomes `__DIR__ . '/../../'` here.
 *
 * After this include the global `$mysqli_db` is populated (db/db.php) and the
 * BugPipeline\BugReportRepository class autoloads from the worktree's local
 * classes/ dir (the realpath block below forces local classes/ over the vendor
 * symlink — without it PHPStan/runtime can resolve the wrong repository).
 */

// ── CLI-only guard ──────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    echo 'This script must be run from the command line.';
    exit(1);
}

// ── Minimal bootstrap (mirrors scripts/runEngineShadow.php) ───────────────────
$_SERVER['PHP_SELF'] = 'bug-pipeline';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require_once __DIR__ . '/../../vendor/autoload.php';

// Worktree fix: vendor/ symlinks to the main repo, so PSR-4 resolves classes/
// there; register the local classes/ dir so this worktree's code is used.
$localClassesDir = realpath(__DIR__ . '/../../classes');
if ($localClassesDir !== false) {
    spl_autoload_register(static function (string $class) use ($localClassesDir): void {
        $path = $localClassesDir . '/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    });
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db/db.php';

/** @var \mysqli $mysqli_db */
