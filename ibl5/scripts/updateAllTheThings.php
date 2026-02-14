<?php
error_reporting(E_ALL);
libxml_use_internal_errors(true);

// Load mainfile first for authentication
require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

// SECURITY: Admin-only script - check authentication before proceeding
if (!function_exists('is_admin') || !is_admin($admin)) {
    header('HTTP/1.1 403 Forbidden');
    die('Access denied. This script requires administrator privileges.');
}

// Allow up to 5 minutes for the full update (production shared hosting has 30s default)
set_time_limit(300);

// --- Progressive output: disable every buffering layer ---

// 1. Disable PHP-level gzip compression (ignores flush() when enabled)
@ini_set('zlib.output_compression', '0');

// 2. Disable Apache mod_deflate for this response
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}

// 3. Flush and destroy all PHP output buffers (e.g. from session_start, mainfile.php)
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

// 4. Tell proxies/servers not to buffer
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Accel-Buffering: no');          // nginx proxy buffering
    header('Content-Encoding: none');          // prevent mod_deflate compression
    header('Cache-Control: no-cache');         // prevent proxy caching
}

// 5. Send initial padding — browsers won't start rendering until ~1 KB is received
echo '<!DOCTYPE html><html><head><title>Updating…</title></head><body>';
echo str_repeat(' ', 1024);
flush();

// Set up error handler to catch all errors
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    echo "<p style='color: red;'><b>ERROR [$errno]:</b> $errstr in $errfile on line $errline</p>";
    flush();
    return false;
});

// Set up exception handler
set_exception_handler(function ($exception) {
    echo "<p style='color: red;'><b>EXCEPTION:</b> " . htmlspecialchars($exception->getMessage()) . " in " . htmlspecialchars($exception->getFile()) . " on line " . $exception->getLine() . "</p>";
    echo "<pre style='color: red;'>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
    flush();
});

try {
    // mainfile.php already loaded above for auth check

    global $mysqli_db;

    echo "<p>✓ mainfile.php loaded</p>";
    flush();

    $commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
    echo "<p>✓ CommonRepository initialized</p>";
    flush();

    $sharedFunctions = new Shared($mysqli_db);
    echo "<p>✓ Shared functions initialized</p>";
    flush();

    $season = new Season($mysqli_db);
    echo "<p>✓ Season initialized</p>";
    flush();

    // Initialize components
    $scheduleUpdater = new Updater\ScheduleUpdater($mysqli_db, $season);
    echo "<p>✓ ScheduleUpdater initialized</p>";
    flush();

    $standingsUpdater = new Updater\StandingsUpdater($mysqli_db, $commonRepository);
    echo "<p>✓ StandingsUpdater initialized</p>";
    flush();

    $powerRankingsUpdater = new Updater\PowerRankingsUpdater($mysqli_db, $season);
    echo "<p>✓ PowerRankingsUpdater initialized</p>";
    flush();

    // Update schedule
    echo "<p>Updating schedule...</p>";
    flush();
    $scheduleUpdater->update();
    echo "<p>✓ Schedule updated</p>";
    flush();

    // Update standings
    echo "<p>Updating standings...</p>";
    flush();
    $standingsUpdater->update();
    echo "<p>✓ Standings updated</p>";
    flush();

    // Update power rankings
    echo "<p>Updating power rankings...</p>";
    flush();
    $powerRankingsUpdater->update();
    echo "<p>✓ Power rankings updated</p>";
    flush();

    // Reset extension attempts
    echo "<p>Resetting extension attempts...</p>";
    flush();
    $sharedFunctions->resetSimContractExtensionAttempts();
    echo "<p>✓ Extension attempts reset</p>";
    flush();

    // Extend active saved depth charts
    echo "<p>Updating saved depth charts...</p>";
    flush();
    $savedDcUpdater = new Updater\SavedDepthChartUpdater($mysqli_db);
    $freshSeason = new Season($mysqli_db);
    $savedDcCount = $savedDcUpdater->update($freshSeason->lastSimEndDate, $freshSeason->lastSimNumber);
    echo "<p>✓ Saved depth charts updated ($savedDcCount active DCs extended)</p>";
    flush();

    echo '<p><b>All the things have been updated!</b></p>';
    flush();

} catch (Exception $e) {
    echo "<p style='color: red;'><b>CAUGHT EXCEPTION:</b> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre style='color: red;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    flush();
}

echo '<a href="/ibl5/index.php">Return to the IBL homepage</a>';
echo '</body></html>';
flush();
