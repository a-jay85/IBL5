<?php

declare(strict_types=1);

/**
 * Build the native-engine input bundle JSON from the live database and print it
 * to stdout. Pipe it into the Go engine: `php build-engine-bundle.php --year=2026 | jsbsim`.
 *
 * This is PR2 of the JSB native engine program — the production DB→bundle path.
 * It does NOT invoke the engine or write results (that is PR8).
 *
 * Usage:
 *   php build-engine-bundle.php --year=2026
 *   php build-engine-bundle.php --year=2026 --start=2026-03-01 --end=2026-03-31
 *   php build-engine-bundle.php --year=2026 --game-type=4 --seed=12345 --league-id=1
 */

use EngineBundle\BundleSerializer;
use EngineBundle\EngineBundleRepository;
use EngineBundle\EngineBundleService;

// ── CLI-only guard ──────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    echo 'This script must be run from the command line.';
    exit(1);
}

// ── Minimal bootstrap (mirrors scripts/bulkJsbImport.php) ─────────────────────
$_SERVER['PHP_SELF'] = 'build-engine-bundle.php';
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

// ── Parse CLI options ─────────────────────────────────────────────────────────
$opts = getopt('', ['year:', 'start::', 'end::', 'game-type::', 'seed::', 'league-id::']);

$yearOpt = $opts['year'] ?? null;
if (!is_string($yearOpt) || !ctype_digit($yearOpt)) {
    fwrite(STDERR, "build-engine-bundle: --year=<season_year> is required (integer).\n");
    exit(1);
}

$intOpt = static function (string $key) use ($opts): ?int {
    $v = $opts[$key] ?? null;
    return is_string($v) && ($v === '0' || ctype_digit(ltrim($v, '-'))) ? (int) $v : null;
};
$strOpt = static function (string $key) use ($opts): ?string {
    $v = $opts[$key] ?? null;
    return is_string($v) && $v !== '' ? $v : null;
};

$service = new EngineBundleService(
    new EngineBundleRepository($mysqli_db),
    new BundleSerializer(),
);

try {
    echo $service->buildBundleJson(
        (int) $yearOpt,
        $strOpt('start'),
        $strOpt('end'),
        $intOpt('game-type') ?? EngineBundleService::DEFAULT_GAME_TYPE,
        $intOpt('seed'),
        $intOpt('league-id') ?? EngineBundleService::DEFAULT_LEAGUE_ID,
    ), PHP_EOL;
} catch (\EngineBundle\EmptyScheduleException | \EngineBundle\EmptyRosterException $e) {
    fwrite(STDERR, 'build-engine-bundle: ' . $e->getMessage() . "\n");
    exit(1);
}
