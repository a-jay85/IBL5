<?php

declare(strict_types=1);

/**
 * CLI-only prod cron worker: drains the Google Sheet refresh queue.
 *
 * The post-sim pipeline step (Updater\Steps\QueueGoogleSheetRefreshStep) only
 * marks connections pending; this worker writes them, one GM at a time, behind
 * GoogleSheetExportService::refreshConnection()'s per-GM isolation boundary.
 *
 * Usage: php ibl5/scripts/googleSheetRefreshTick.php [flags]
 *   --dry-run       List pending rows and the row count to write; no Google calls.
 *   --all           Mark every active connection pending before draining (ignored with --dry-run).
 *   --limit=N       Max rows per tick (default 100).
 *   --budget=N      Stop starting rows after N seconds (default 240).
 *   --print-cron    Print the crontab line for this script and exit 0.
 *   --help          Print usage and exit 0.
 *
 * Exit codes: 0 ok or nothing pending, 1 a row ended error/broken or the DB
 * was unavailable, 2 bad arguments, 3 secret key or OAuth config unavailable.
 *
 * Tokens are never printed: output carries only user ids, statuses, and counts.
 */

// CLI-only guard: must stay the FIRST executable statement.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script is CLI-only.');
}

const TICK_DEFAULT_LIMIT = 100;
const TICK_DEFAULT_BUDGET = 240;

function tickUsage(): string
{
    return "Usage: php googleSheetRefreshTick.php [--dry-run] [--all] [--limit=N] [--budget=N] [--print-cron] [--help]\n";
}

// Argument validation and --print-cron run BEFORE the DB bootstrap and env wiring,
// so they work without any environment variables.
global $argv;
/** @var list<string> $argv */
$tickArgs = array_slice($argv, 1);
$limit = TICK_DEFAULT_LIMIT;
$budget = TICK_DEFAULT_BUDGET;
$dryRun = false;
$all = false;
$printCron = false;
$help = false;

foreach ($tickArgs as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif ($arg === '--all') {
        $all = true;
    } elseif ($arg === '--print-cron') {
        $printCron = true;
    } elseif ($arg === '--help') {
        $help = true;
    } elseif (preg_match('/^--limit=(\d+)$/', $arg, $m) === 1) {
        $limit = (int) $m[1];
    } elseif (preg_match('/^--budget=(\d+)$/', $arg, $m) === 1) {
        $budget = (int) $m[1];
    } else {
        fwrite(STDERR, tickUsage());
        exit(2);
    }
}

if ($help) {
    fwrite(STDOUT, tickUsage());
    exit(0);
}

if ($limit < 1 || $budget < 1) {
    fwrite(STDERR, tickUsage());
    exit(2);
}

if ($printCron) {
    $scriptPath = realpath(__FILE__);
    $logDir = realpath(__DIR__ . '/../logs');
    if ($scriptPath === false) {
        fwrite(STDERR, "googleSheetRefreshTick: cannot resolve script path\n");
        exit(1);
    }
    if ($logDir === false) {
        $logDir = dirname(__DIR__) . '/logs';
    }
    fwrite(STDOUT, '*/5 * * * * ' . PHP_BINARY . ' ' . $scriptPath . ' >> ' . $logDir . "/google-sheet-tick.log 2>&1\n");
    exit(0);
}

// Overlap guard: a second tick exits quietly while one holds the lock.
$lockHandle = fopen(sys_get_temp_dir() . '/ibl5-google-sheet-tick.lock', 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, "already running\n");
    exit(0);
}

// Minimal bootstrap (mirrors scripts/simRecapQueue.php).
$_SERVER['PHP_SELF'] = 'googleSheetRefreshTick';
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
$db = $mysqli_db;

try {
    \Logging\LoggerFactory::fromConfig();
    $logger = \Logging\LoggerFactory::getChannel('google-sheets');

    $config = \GoogleSheets\GoogleOAuthConfig::fromEnv('iblhoops.net', true);
    $box = \Security\SecretBox::fromEnv();
    $http = new \GoogleSheets\CurlGoogleHttpClient();
    $repo = new \GoogleSheets\GoogleSheetConnectionRepository($db);
    $export = new \GoogleSheets\GoogleSheetExportService(
        $repo,
        new \GoogleSheets\GoogleOAuthClient($config, $http),
        new \GoogleSheets\GoogleSheetsClient($http),
        $box,
        new \Api\Repository\ApiPlayerRepository($db),
        new \Api\Transformer\PlayerExportTransformer(),
        $logger
    );
} catch (\Security\SecretBoxKeyUnavailableException | \GoogleSheets\GoogleOAuthNotConfiguredException $e) {
    // Touch no row: the queue stays pending until the environment is fixed.
    fwrite(STDERR, 'googleSheetRefreshTick: ' . $e->getMessage() . "\n");
    exit(3);
}

$worker = new \GoogleSheets\GoogleSheetRefreshWorker($repo, $export, new \Clock\SystemClock(), $logger);

try {
    $exitCode = $worker->run(
        $limit,
        $budget,
        $dryRun,
        $all,
        static function (string $line): void {
            fwrite(STDOUT, $line . "\n");
        }
    );
} catch (\Throwable $e) {
    // Missing table or DB error: the exception class only, never a message that could carry a DSN.
    fwrite(STDERR, 'googleSheetRefreshTick: queue unavailable: ' . get_class($e) . "\n");
    exit(1);
}

exit($exitCode);
