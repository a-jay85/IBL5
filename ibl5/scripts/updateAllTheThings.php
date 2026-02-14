<?php

declare(strict_types=1);

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

global $mysqli_db;

$view = new Updater\UpdaterView();

$stylesheetPath = '/ibl5/themes/IBL/style/style.css';
/** @var int|false $stylesheetMtime */
$stylesheetMtime = filemtime($_SERVER['DOCUMENT_ROOT'] . $stylesheetPath);
$cacheBuster = $stylesheetMtime !== false ? '?v=' . $stylesheetMtime : '';

echo $view->renderPageOpen($stylesheetPath . $cacheBuster);
flush();

// Set up error handler to catch all errors with XSS-safe output
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) use ($view): bool {
    /** @var string $safeMessage */
    $safeMessage = \Utilities\HtmlSanitizer::safeHtmlOutput(
        "[$errno] $errstr in $errfile on line $errline"
    );
    echo $view->renderStepError('PHP Error', (string) $safeMessage);
    flush();
    return false;
});

// Set up exception handler with XSS-safe output
set_exception_handler(function (\Throwable $exception) use ($view): void {
    /** @var string $safeMessage */
    $safeMessage = \Utilities\HtmlSanitizer::safeHtmlOutput($exception->getMessage());
    /** @var string $safeFile */
    $safeFile = \Utilities\HtmlSanitizer::safeHtmlOutput($exception->getFile());
    /** @var string $safeTrace */
    $safeTrace = \Utilities\HtmlSanitizer::safeHtmlOutput($exception->getTraceAsString());

    echo $view->renderStepError(
        'Uncaught Exception',
        (string) $safeMessage . ' in ' . (string) $safeFile . ' on line ' . $exception->getLine()
    );
    echo $view->renderLog((string) $safeTrace);
    flush();
});

$successCount = 0;
$errorCount = 0;

try {
    // --- Initialization ---
    echo $view->renderInitStatus('mainfile.php loaded');
    flush();

    $commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
    echo $view->renderInitStatus('CommonRepository initialized');
    flush();

    $sharedFunctions = new \Shared($mysqli_db);
    echo $view->renderInitStatus('Shared functions initialized');
    flush();

    $season = new \Season($mysqli_db);
    echo $view->renderInitStatus('Season initialized');
    flush();

    $scheduleUpdater = new Updater\ScheduleUpdater($mysqli_db, $season);
    echo $view->renderInitStatus('ScheduleUpdater initialized');
    flush();

    $standingsUpdater = new Updater\StandingsUpdater($mysqli_db, $season);
    echo $view->renderInitStatus('StandingsUpdater initialized');
    flush();

    $powerRankingsUpdater = new Updater\PowerRankingsUpdater($mysqli_db, $season);
    echo $view->renderInitStatus('PowerRankingsUpdater initialized');
    flush();

    // --- Step 1: Update schedule ---
    echo $view->renderStepStart('Updating schedule...');
    flush();
    ob_start();
    $scheduleUpdater->update();
    $log = (string) ob_get_clean();
    echo $view->renderStepComplete('Schedule updated');
    if ($log !== '') {
        echo $view->renderLog($log);
    }
    flush();
    $successCount++;

    // --- Step 2: Update standings ---
    echo $view->renderStepStart('Updating standings...');
    flush();
    ob_start();
    $standingsUpdater->update();
    $log = (string) ob_get_clean();
    echo $view->renderStepComplete('Standings updated');
    if ($log !== '') {
        echo $view->renderLog($log);
    }
    flush();
    $successCount++;

    // --- Step 3: Update power rankings ---
    echo $view->renderStepStart('Updating power rankings...');
    flush();
    ob_start();
    $powerRankingsUpdater->update();
    $log = (string) ob_get_clean();
    echo $view->renderStepComplete('Power rankings updated');
    if ($log !== '') {
        echo $view->renderLog($log);
    }
    flush();
    $successCount++;

    // --- Step 4: Reset extension attempts ---
    echo $view->renderStepStart('Resetting extension attempts...');
    flush();
    $sharedFunctions->resetSimContractExtensionAttempts();
    echo $view->renderStepComplete('Extension attempts reset');
    flush();
    $successCount++;

    // --- Step 5: Extend active saved depth charts ---
    echo $view->renderStepStart('Updating saved depth charts...');
    flush();
    $savedDcUpdater = new Updater\SavedDepthChartUpdater($mysqli_db);
    ob_start();
    $savedDcCount = $savedDcUpdater->update($season->lastSimEndDate, $season->lastSimNumber);
    $log = (string) ob_get_clean();
    echo $view->renderStepComplete('Saved depth charts updated', $savedDcCount . ' active DCs extended');
    if ($log !== '') {
        echo $view->renderLog($log);
    }
    flush();
    $successCount++;

    echo $view->renderSummary($successCount, $errorCount);
    flush();

} catch (\Exception $e) {
    $errorCount++;
    /** @var string $safeMessage */
    $safeMessage = \Utilities\HtmlSanitizer::safeHtmlOutput($e->getMessage());
    /** @var string $safeTrace */
    $safeTrace = \Utilities\HtmlSanitizer::safeHtmlOutput($e->getTraceAsString());

    echo $view->renderStepError('Exception', (string) $safeMessage);
    echo $view->renderLog((string) $safeTrace);
    echo $view->renderSummary($successCount, $errorCount);
    flush();
}

echo $view->renderPageClose();
flush();
