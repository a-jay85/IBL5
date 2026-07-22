<?php

declare(strict_types=1);

/**
 * CLI-only writer for the sim-recap pipeline.
 *
 * Usage: php ibl5/scripts/storeSimRecap.php --sim=N
 *   Recap prose is read from stdin (never argv — prose is multi-KB, multi-line,
 *   and would be visible in `ps`).
 *
 * This is the single privileged writer for ibl_sim_summaries (security constraint 3).
 * Protected by both a PHP_SAPI guard (below, first executable statement) and
 * ibl5/scripts/.htaccess (a <Files "storeSimRecap.php"> scoped deny).
 */

// ── CLI-only guard (security constraint 4) — must stay the FIRST executable
//    statement: a web hit must be refused before any resource is touched.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'This script must be run from the command line.';
    exit(1);
}

// ── Minimal bootstrap (mirrors scripts/runEngineShadow.php) ───────────────────
$_SERVER['PHP_SELF'] = 'storeSimRecap';
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
    fwrite(STDERR, "storeSimRecap: {$msg}\n");
    exit(1);
}

// ── Argv parse ────────────────────────────────────────────────────────────────
$raw = null;
foreach (array_slice($argv, 1) as $arg) {
    if (!is_string($arg)) {
        continue;
    }
    if (str_starts_with($arg, '--sim=')) {
        $raw = substr($arg, 6);
    } else {
        fail("unknown argument: {$arg}");
    }
}

if ($raw === null) {
    fail('--sim=N is required');
}
if (!ctype_digit($raw)) {
    fail("--sim must be a positive integer, got: {$raw}");
}
$sim = (int) $raw;
if ($sim < 1) {
    fail('--sim must be >= 1');
}

// ── Read stdin ────────────────────────────────────────────────────────────────
$recap = stream_get_contents(STDIN);
$recap = is_string($recap) ? trim($recap) : '';
if ($recap === '') {
    fail('no recap text on stdin');
}

// ── Write, then confirm ───────────────────────────────────────────────────────
$repo = new \SimRecap\SimSummaryRepository($mysqli_db);
$repo->markDone($sim, $recap, null);
$row = $repo->find($sim);
if ($row === null || $row['status'] !== 'done') {
    fail("store failed: sim {$sim} is not in state done after write");
}

// ── Post to Discord, only after a confirmed write ─────────────────────────────
\Discord\Discord::postToChannel('#admin-chat', "Sim {$sim} recap is ready for review.");

// ── Success output ────────────────────────────────────────────────────────────
echo json_encode(['ok' => true, 'sim' => $sim, 'bytes' => strlen($recap)]), "\n";
exit(0);
