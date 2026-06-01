<?php

declare(strict_types=1);

/**
 * Run the native-engine SHADOW sim for a full season, out-of-band.
 *
 * This is the detached CLI entry point spawned fire-and-forget by
 * EngineShadow\ShadowProcessLauncher from updateAllTheThings.php (and cron-ready).
 * It builds the IBL engine bundle, streams the engine's NDJSON output one game at
 * a time, and loads each game into the droppable shadow box-score tables. It never
 * touches canonical tables. Process separation — not try/catch in a web request —
 * provides failure isolation: a crash/timeout/OOM here cannot affect the admin's
 * synchronous "Update All The Things" run.
 *
 * Usage:
 *   php runEngineShadow.php            # season resolved from the DB (Season->endingYear)
 *   php runEngineShadow.php --year=2026
 *
 * Exit codes: 0 = success OR no-work (empty schedule/roster) OR lock contention;
 *             1 = engine/loader/DB failure.
 */

use EngineBundle\BundleSerializer;
use EngineBundle\EmptyRosterException;
use EngineBundle\EmptyScheduleException;
use EngineBundle\EngineBundleRepository;
use EngineBundle\EngineBundleService;
use EngineRunner\EngineRunner;
use EngineShadow\EngineShadowLoader;
use EngineShadow\EngineShadowRepository;
use EngineShadow\EngineShadowRunService;

// ── CLI-only guard ──────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    echo 'This script must be run from the command line.';
    exit(1);
}

// ── Minimal bootstrap (mirrors scripts/build-engine-bundle.php) ───────────────
$_SERVER['PHP_SELF'] = 'runEngineShadow.php';
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

// ── Non-blocking single-run lock: overlapping runs are a benign skip ──────────
$lockPath = sys_get_temp_dir() . '/ibl5-engine-shadow.lock';
$lockHandle = fopen($lockPath, 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "runEngineShadow: another run holds the lock; skipping.\n");
    exit(0);
}

// ── Resolve season year: --year override, else Season->endingYear (parity with
// the removed inline EngineShadowStep). IBL-scoped (null LeagueContext). ───────
$opts = getopt('', ['year::']);
$yearOpt = $opts['year'] ?? null;
$seasonYear = is_string($yearOpt) && ctype_digit($yearOpt)
    ? (int) $yearOpt
    : (new Season\Season($mysqli_db))->endingYear;

// ── Wire dependencies (IBL default — null LeagueContext) ──────────────────────
$bundleService = new EngineBundleService(
    new EngineBundleRepository($mysqli_db),
    new BundleSerializer(),
);
$shadowRepository = new EngineShadowRepository($mysqli_db);
$runService = new EngineShadowRunService(
    $bundleService,
    new EngineRunner(),
    new EngineShadowLoader($shadowRepository),
    $shadowRepository,
);

try {
    $summary = $runService->runForSeason($seasonYear);
    fwrite(
        STDOUT,
        sprintf(
            "%d games shadow-simmed (seed %d) for season %d\n",
            $summary->gamesLoaded,
            $summary->seed,
            $seasonYear,
        ),
    );
    exit(0);
} catch (EmptyScheduleException | EmptyRosterException $e) {
    // No-work is success: nothing to shadow-sim for this season.
    fwrite(STDERR, 'runEngineShadow: nothing to do: ' . $e->getMessage() . "\n");
    exit(0);
} catch (\Throwable $e) {
    // The single catch point — process separation isolates the failure from the
    // admin request that spawned us.
    fwrite(STDERR, 'runEngineShadow: shadow run failed: ' . $e->getMessage() . "\n");
    exit(1);
}
