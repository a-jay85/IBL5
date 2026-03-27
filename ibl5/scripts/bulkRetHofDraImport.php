<?php

declare(strict_types=1);

/**
 * Bulk Draft / Retired / Hall of Fame Import Script
 *
 * Parses .dra, .ret, and .hof files from the IBL0607HEATend archive
 * and imports them into ibl_jsb_draft_results, ibl_jsb_retired_players,
 * and ibl_jsb_hall_of_fame tables.
 *
 * Usage:
 *   php bulkRetHofDraImport.php                     # Full processing mode
 *   php bulkRetHofDraImport.php --dry-run           # List files only
 *   php bulkRetHofDraImport.php --file-type=dra     # Only process .dra files
 *   php bulkRetHofDraImport.php --file-type=ret     # Only process .ret files
 *   php bulkRetHofDraImport.php --file-type=hof     # Only process .hof files
 */

// ── CLI-only guard ──────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    echo 'This script must be run from the command line.';
    exit(1);
}

// ── Minimal bootstrap (avoids web-only mainfile.php) ────────────────────────
$_SERVER['PHP_SELF'] = 'bulkRetHofDraImport.php';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require_once __DIR__ . '/../autoloader.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/db.php';

/** @var \mysqli $mysqli_db */

// ── Parse CLI options ───────────────────────────────────────────────────────
$dryRun = in_array('--dry-run', $argv, true);

$fileTypeFilter = null;
$validTypes = ['dra', 'ret', 'hof'];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--file-type=')) {
        $fileTypeFilter = substr($arg, strlen('--file-type='));
        if (!in_array($fileTypeFilter, $validTypes, true)) {
            echo "Invalid file type: {$fileTypeFilter}. Valid types: " . implode(', ', $validTypes) . "\n";
            exit(1);
        }
    }
}

// ── Target directory ─────────────────────────────────────────────────────────
$targetDir = dirname(__DIR__) . '/fullLeagueBackups/IBL0607HEATend';
if (!is_dir($targetDir)) {
    echo "Target directory not found: {$targetDir}\n";
    exit(1);
}

// ── File mapping ─────────────────────────────────────────────────────────────
$fileMap = [
    'dra' => $targetDir . '/IBL5.dra',
    'ret' => $targetDir . '/IBL5.ret',
    'hof' => $targetDir . '/IBL5.hof',
];

$fileTypes = $fileTypeFilter !== null ? [$fileTypeFilter] : ['dra', 'ret', 'hof'];

// ── Dry run: list files and exit ────────────────────────────────────────────
if ($dryRun) {
    echo "Target: {$targetDir}\n\n";
    echo str_pad('File Type', 12) . str_pad('File', 40) . "Exists\n";
    echo str_repeat('-', 60) . "\n";

    foreach ($fileTypes as $type) {
        $path = $fileMap[$type];
        $exists = file_exists($path) ? 'Y' : '-';
        echo str_pad('.' . $type, 12) . str_pad(basename($path), 40) . $exists . "\n";
    }
    exit(0);
}

// ── Process files ───────────────────────────────────────────────────────────
$repository = new JsbParser\JsbImportRepository($mysqli_db);
$resolver = new JsbParser\PlayerIdResolver($mysqli_db);
$service = new JsbParser\JsbImportService($repository, $resolver);

$totalInserted = 0;
$totalUpdated = 0;
$totalSkipped = 0;
$totalErrors = 0;
$filesProcessed = 0;

foreach ($fileTypes as $fileType) {
    $filePath = $fileMap[$fileType];

    if (!file_exists($filePath)) {
        echo "Skipping .{$fileType}: file not found at {$filePath}\n";
        continue;
    }

    echo sprintf("Processing .%s — %s\n", $fileType, $filePath);

    try {
        $result = match ($fileType) {
            'dra' => $service->processDraFile($filePath),
            'ret' => $service->processRetFile($filePath),
            'hof' => $service->processHofFile($filePath),
        };

        echo "    {$result->summary()}\n";

        foreach ($result->messages as $msg) {
            echo "    {$msg}\n";
        }

        $totalInserted += $result->inserted;
        $totalUpdated += $result->updated;
        $totalSkipped += $result->skipped;
        $totalErrors += $result->errors;
        $filesProcessed++;
    } catch (\Throwable $e) {
        echo "    ERROR: {$e->getMessage()}\n";
        $totalErrors++;
    }
}

// ── Final summary ───────────────────────────────────────────────────────────
echo "\n" . str_repeat('=', 50) . "\n";
echo "BULK DRA/RET/HOF IMPORT COMPLETE\n";
echo str_repeat('=', 50) . "\n";
echo sprintf("Files processed: %d\n", $filesProcessed);
echo sprintf("Records inserted:  %d\n", $totalInserted);
echo sprintf("Records updated:   %d\n", $totalUpdated);
echo sprintf("Records skipped:   %d\n", $totalSkipped);
if ($totalErrors > 0) {
    echo sprintf("Errors:            %d\n", $totalErrors);
}
echo str_repeat('=', 50) . "\n";
