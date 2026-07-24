<?php

declare(strict_types=1);

/**
 * CLI-only writer for the sim-recap pipeline.
 *
 * Usage: php ibl5/scripts/storeSimRecap.php --sim=N
 *   A structured JSON recap document is read from stdin (never argv — the prose
 *   is multi-KB, multi-line, and would be visible in `ps`). Parsing and
 *   validation live in SimRecap\SimRecapPayload, which fails closed.
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

// ── Read + parse stdin (structured JSON — fail closed) ────────────────────────
$json = stream_get_contents(STDIN);
if (!is_string($json) || trim($json) === '') {
    fail('no JSON payload on stdin');
}
try {
    $payload = \SimRecap\SimRecapPayload::fromJson($json);
} catch (\Throwable $e) {
    fail('malformed payload: ' . $e->getMessage());
}

// ── Write, then confirm ───────────────────────────────────────────────────────
$repo = new \SimRecap\SimSummaryRepository($mysqli_db);
$repo->markDone(
    $sim,
    $payload->getIntroText(),
    $payload->getOutroText(),
    $payload->getRecapText(),
    $payload->getGames(),
    $payload->getThemesJson()
);
$row = $repo->find($sim);
if ($row === null || $row['status'] !== 'done') {
    fail("store failed: sim {$sim} is not in state done after write");
}

// ── Resolve the canonical host (prod-side config only, never from argv) ───────
// One value feeds both jobs below. A real bare hostname makes Discord's
// isProduction() true so the post routes to #admin-chat; an empty host — CI,
// dev, any machine without the constant — leaves it on the testing webhook and
// keeps the link relative. Empty is the safe default, never an error.
$rawHost = defined('IBL5_CANONICAL_HOST') ? constant('IBL5_CANONICAL_HOST') : '';
$host = is_string($rawHost) ? $rawHost : '';

\Discord\Discord::init($host);

$viewerUrl = $host === ''
    ? \SimRecap\SimSummaryLink::path($sim)
    : \SimRecap\SimSummaryLink::absolute($sim, $host);

// ── Post to Discord, only after a confirmed write ─────────────────────────────
\Discord\Discord::postToChannel('#admin-chat', "Sim {$sim} recap is ready for review: {$viewerUrl}");

// ── Success output ────────────────────────────────────────────────────────────
echo json_encode(['ok' => true, 'sim' => $sim, 'games' => count($payload->getGames()), 'url' => $viewerUrl]), "\n";
exit(0);
