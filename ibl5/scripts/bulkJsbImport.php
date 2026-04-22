<?php

declare(strict_types=1);

/**
 * Unified Bulk JSB File Import Script
 *
 * Imports all JSB engine file types from season archives into the database.
 * Handles 13 file types: .trn, .car, .his, .asw, .awa, .rcb, .sco, .dra,
 * .ret, .hof, .lge (cumulative — final archive per season), and .plr, .plb
 * (snapshot — every archive per season).
 *
 * Reads season archives from backups/ (zip files organized by season label).
 *
 * Usage:
 *   php bulkJsbImport.php                          # All types, all seasons
 *   php bulkJsbImport.php --dry-run                # List what would be processed
 *   php bulkJsbImport.php --file-type=car          # Single type only
 *   php bulkJsbImport.php --season=00-01           # Single season only
 *   php bulkJsbImport.php --verify                 # Verify cumulative file integrity (no DB writes)
 *   php bulkJsbImport.php --verify --season=24-25  # Verify single season
 */

use BulkImport\BulkImportRunner;
use BulkImport\FileTypeHandler;
use BulkImport\JsbFileType;

// ── CLI-only guard ──────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    echo 'This script must be run from the command line.';
    exit(1);
}

// ── Minimal bootstrap ────────────────────────────────────────────────────────
$_SERVER['PHP_SELF'] = 'bulkJsbImport.php';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require_once __DIR__ . '/../vendor/autoload.php';

// Worktree fix: vendor/ symlinks to main repo, so PSR-4 resolves classes/ there.
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

// ── Parse CLI options ───────────────────────────────────────────────────────
$dryRun = in_array('--dry-run', $argv, true);
$verify = in_array('--verify', $argv, true);

$fileTypeFilter = null;
$seasonFilter = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--file-type=')) {
        $fileTypeFilter = substr($arg, strlen('--file-type='));
        $fileType = JsbFileType::tryFrom($fileTypeFilter);
        if ($fileType === null) {
            echo "Invalid file type: {$fileTypeFilter}\n";
            echo "Valid types: " . implode(', ', JsbFileType::allValid()) . "\n";
            exit(1);
        }
        if ($verify && !$fileType->supportsVerify()) {
            echo "Error: --verify is not supported for snapshot type '{$fileTypeFilter}'\n";
            echo "Snapshot types (.plr, .plb) change every sim — verification is not meaningful.\n";
            exit(1);
        }
    }
    if (str_starts_with($arg, '--season=')) {
        $seasonFilter = substr($arg, strlen('--season='));
    }
}

// ── Determine file types to process ─────────────────────────────────────────
if ($fileTypeFilter !== null) {
    $fileTypes = [JsbFileType::from($fileTypeFilter)];
} elseif ($verify) {
    // Verify mode without --file-type: only cumulative types
    $fileTypes = array_values(array_filter(
        JsbFileType::sortedByImportOrder(),
        static fn (JsbFileType $t): bool => $t->supportsVerify(),
    ));
} else {
    $fileTypes = JsbFileType::sortedByImportOrder();
}

// ── Locate backups directory ────────────────────────────────────────────────
$backupsDir = dirname(__DIR__) . '/backups';

if (!is_dir($backupsDir)) {
    echo "No backup directory found (expected backups/).\n";
    exit(1);
}

$typeLabel = $fileTypeFilter ?? 'all (' . count($fileTypes) . ' types)';
echo sprintf("Types: %s | Source: %s\n", $typeLabel, $backupsDir);

if ($verify) {
    echo "VERIFY MODE: Files will be parsed but no changes will be committed.\n";
}

// ── Construct services ──────────────────────────────────────────────────────
$extractor = new BulkImport\ArchiveExtractor();
$repository = new JsbParser\JsbImportRepository($mysqli_db);
$resolver = new JsbParser\PlayerIdResolver($mysqli_db);
$jsbService = new JsbParser\JsbImportService($repository, $resolver);
$boxscoreProcessor = new Boxscore\BoxscoreProcessor($mysqli_db);
$plrRepo = new PlrParser\PlrParserRepository($mysqli_db);
$commonRepo = new Services\CommonMysqliRepository($mysqli_db);
$season = new Season\Season($mysqli_db);
$plrService = new PlrParser\PlrParserService($plrRepo, $commonRepo, $season);
$lgeRepo = new LeagueConfig\LeagueConfigRepository($mysqli_db);
$lgeService = new LeagueConfig\LeagueConfigService($lgeRepo);

$handler = new FileTypeHandler($jsbService, $boxscoreProcessor, $plrService, $lgeService);
$runner = new BulkImportRunner($extractor, $handler, $resolver, $mysqli_db);

// ── Run ─────────────────────────────────────────────────────────────────────
$summary = $runner->run($backupsDir, $fileTypes, $dryRun, $verify, $seasonFilter);

if (!$dryRun) {
    $title = $verify ? 'VERIFICATION COMPLETE' : 'BULK IMPORT COMPLETE';
    $summary->printSummary($title);
}

exit($summary->hasErrors() ? 1 : 0);
