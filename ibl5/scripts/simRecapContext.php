<?php

declare(strict_types=1);

/**
 * CLI-only roster-context precompute for the sim-recap pipeline.
 *
 * Usage: php ibl5/scripts/simRecapContext.php --sim=N
 *   Emits one JSON object on stdout containing current rosters, active injuries,
 *   and in-window trades for the given sim. A bad argv writes an error to stderr
 *   and exits 1 before any repo is built.
 *
 * ADR-0093 trust boundary: this script is the prod-side half — it holds the
 * SELECT-only credential via db/db.php (injected into MYSQL_PWD by the calling
 * environment) and is invoked over SSH. It accepts NO credential flag, reads NO
 * credential env var, and hardcodes nothing. The Mac-side tick calls this script
 * via SSH and never holds the password itself.
 *
 * Protected by both the PHP_SAPI guard below (the first executable statement)
 * and an ibl5/scripts/.htaccess scoped deny.
 */

// ── CLI-only guard — must stay the FIRST executable statement: a web hit must
//    be refused before any resource (autoload, config, db) is touched.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script is CLI-only.');
}

// ── Minimal bootstrap (mirrors scripts/simRecapQueue.php) ─────────────────────
$_SERVER['PHP_SELF'] = 'simRecapContext';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require_once __DIR__ . '/../vendor/autoload.php';

// Worktree fix: vendor/ symlinks to the main repo, so PSR-4 resolves classes/
// there; register the local classes/ dir so this worktree's code is used.
$localClassesDir = realpath(__DIR__ . '/../classes');
if ($localClassesDir !== false) {
    spl_autoload_register(static function (string $class) use ($localClassesDir): void {
        $path = $localClassesDir . '/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    });
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/db.php';

/** @var \mysqli $mysqli_db */

function fail(string $msg): never
{
    fwrite(STDERR, "simRecapContext: {$msg}\n");
    exit(1);
}

// ── Argv parse ────────────────────────────────────────────────────────────────
$simRaw = null;
foreach (array_slice($argv, 1) as $arg) {
    if (!is_string($arg)) {
        continue;
    }
    if (str_starts_with($arg, '--sim=')) {
        $simRaw = substr($arg, 6);
    } else {
        fail("unexpected argument: {$arg}");
    }
}

if ($simRaw === null || !ctype_digit($simRaw)) {
    fail('--sim=N (positive integer) is required');
}

$sim = (int) $simRaw;
if ($sim < 1) {
    fail('--sim must be >= 1');
}

// ── Build and emit context ────────────────────────────────────────────────────
$repo = new \SimRecap\SimRecapContextRepository($mysqli_db);
echo json_encode($repo->buildContext($sim)), "\n";
exit(0);
