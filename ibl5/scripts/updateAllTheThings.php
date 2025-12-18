<?php
error_reporting(E_ALL);
libxml_use_internal_errors(true);

// Set up error handler to catch all errors
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    echo "<p style='color: red;'><b>ERROR [$errno]:</b> $errstr in $errfile on line $errline</p>";
    return false;
});

// Set up exception handler
set_exception_handler(function ($exception) {
    echo "<p style='color: red;'><b>EXCEPTION:</b> " . htmlspecialchars($exception->getMessage()) . " in " . htmlspecialchars($exception->getFile()) . " on line " . $exception->getLine() . "</p>";
    echo "<pre style='color: red;'>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
});

try {
    require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

    global $mysqli_db;

    echo "<p>✓ mainfile.php loaded</p>";
    
    $commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
    echo "<p>✓ CommonRepository initialized</p>";
    
    $sharedFunctions = new Shared($mysqli_db);
    echo "<p>✓ Shared functions initialized</p>";
    
    $season = new Season($mysqli_db);
    echo "<p>✓ Season initialized</p>";

    // Initialize components
    $scheduleUpdater = new Updater\ScheduleUpdater($mysqli_db, $commonRepository, $season);
    echo "<p>✓ ScheduleUpdater initialized</p>";
    
    $standingsUpdater = new Updater\StandingsUpdater($mysqli_db, $commonRepository);
    echo "<p>✓ StandingsUpdater initialized</p>";
    
    $powerRankingsUpdater = new Updater\PowerRankingsUpdater($mysqli_db, $season);
    echo "<p>✓ PowerRankingsUpdater initialized</p>";
    
    $standingsHTMLGenerator = new Updater\StandingsHTMLGenerator($mysqli_db);
    echo "<p>✓ StandingsHTMLGenerator initialized</p>";

    // Update schedule
    echo "<p>Updating schedule...</p>";
    $scheduleUpdater->update();
    echo "<p>✓ Schedule updated</p>";

    // Update standings
    echo "<p>Updating standings...</p>";
    $standingsUpdater->update();
    echo "<p>✓ Standings updated</p>";

    // Update power rankings
    echo "<p>Updating power rankings...</p>";
    $powerRankingsUpdater->update();
    echo "<p>✓ Power rankings updated</p>";

    // Generate standings HTML
    echo "<p>Generating standings HTML...</p>";
    $standingsHTMLGenerator->generateStandingsPage();
    echo "<p>✓ Standings HTML generated</p>";

    // Reset extension attempts
    echo "<p>Resetting extension attempts...</p>";
    $sharedFunctions->resetSimContractExtensionAttempts();
    echo "<p>✓ Extension attempts reset</p>";

    echo '<p><b>All the things have been updated!</b></p>';

} catch (Exception $e) {
    echo "<p style='color: red;'><b>CAUGHT EXCEPTION:</b> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre style='color: red;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo '<a href="/ibl5/index.php">Return to the IBL homepage</a>';
