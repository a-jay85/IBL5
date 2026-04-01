<?php

declare(strict_types=1);

/**
 * Bulk PLR Snapshot Import Script
 *
 * Extracts .plr files from ALL archives and stores player rating snapshots
 * in ibl_plr_snapshots. Each snapshot captures ratings, contract state, and
 * identity at a specific point in the season (one per archive/sim).
 *
 * Environment auto-detection:
 *   - fullLeagueBackups/backups/ exists → local mode (archives from dev extraction)
 *   - backups/ exists                   → production mode (zip archives on server)
 *
 * Usage:
 *   php bulkPlrSnapshotImport.php                           # All seasons, all archives
 *   php bulkPlrSnapshotImport.php --dry-run                 # List archives to process
 *   php bulkPlrSnapshotImport.php --season=00-01            # Single season
 */

// ── CLI-only guard ──────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    echo 'This script must be run from the command line.';
    exit(1);
}

// ── Minimal bootstrap ────────────────────────────────────────────────────────
$_SERVER['PHP_SELF'] = 'bulkPlrSnapshotImport.php';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require_once __DIR__ . '/../vendor/autoload.php';

// Worktree fix: vendor/ symlinks to main repo, so PSR-4 resolves classes/ there.
// Register the local classes/ directory to pick up worktree-only classes.
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
$seasonFilter = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--season=')) {
        $seasonFilter = substr($arg, strlen('--season='));
    }
}

// ── Environment auto-detection ──────────────────────────────────────────────
// Local dev: fullLeagueBackups/backups/ has season subdirs with archives
// Production: backups/ has season subdirs with archives (same structure)
$localDir = dirname(__DIR__) . '/fullLeagueBackups/backups';
$prodDir  = dirname(__DIR__) . '/backups';

if (is_dir($localDir)) {
    $backupsDir = $localDir;
    $mode = 'local';
} elseif (is_dir($prodDir)) {
    $backupsDir = $prodDir;
    $mode = 'production';
} else {
    echo "No backup directory found.\n";
    echo "  Expected: fullLeagueBackups/backups/ (local) or backups/ (production)\n";
    exit(1);
}

echo sprintf("Mode: %s (source: %s)\n", $mode, $backupsDir);

$extractor = new BulkImport\ArchiveExtractor();

/** @var list<string>|false $seasonDirs */
$seasonDirs = glob($backupsDir . '/*', GLOB_ONLYDIR);
if ($seasonDirs === false || $seasonDirs === []) {
    echo "No season directories found in backups/\n";
    exit(1);
}

sort($seasonDirs);

// ── Build import plan ───────────────────────────────────────────────────────

/**
 * @var list<array{
 *     season: string,
 *     ending_year: int,
 *     archive: string,
 *     phase: string,
 *     season_dir: string
 * }> $plan
 */
$plan = [];

foreach ($seasonDirs as $dirPath) {
    $seasonLabel = basename($dirPath);

    if ($seasonFilter !== null && $seasonLabel !== $seasonFilter) {
        continue;
    }

    $archives = $extractor->findAllArchives($dirPath);
    if ($archives === []) {
        echo "  WARNING: No parseable archives in '{$seasonLabel}', skipping\n";
        continue;
    }

    foreach ($archives as $archive) {
        $plan[] = [
            'season' => $archive['season'],
            'ending_year' => $archive['ending_year'],
            'archive' => $archive['path'],
            'phase' => $archive['phase'],
            'season_dir' => $dirPath,
        ];
    }
}

if ($plan === []) {
    echo "No seasons matched the filter.\n";
    exit(1);
}

echo sprintf("Found %d snapshot(s) to process\n\n", count($plan));

// ── Dry run ─────────────────────────────────────────────────────────────────
if ($dryRun) {
    echo str_pad('Season', 10) . str_pad('Year', 8)
        . str_pad('Phase', 20) . "Archive\n";
    echo str_repeat('-', 100) . "\n";

    foreach ($plan as $entry) {
        echo str_pad($entry['season'], 10)
            . str_pad((string) $entry['ending_year'], 8)
            . str_pad($entry['phase'], 20)
            . basename($entry['archive'])
            . "\n";
    }

    echo sprintf("\nTotal: %d snapshot(s) to process.\n", count($plan));
    exit(0);
}

// ── Initialize services ─────────────────────────────────────────────────────
$plrRepo = new PlrParser\PlrParserRepository($mysqli_db);
$commonRepo = new Services\CommonMysqliRepository($mysqli_db);
$season = new Season\Season($mysqli_db);
$plrService = new PlrParser\PlrParserService($plrRepo, $commonRepo, $season);

$totalSnapshots = 0;
$totalErrors = 0;
$filesProcessed = 0;

/**
 * Extract a file from an archive, process it, and clean up.
 *
 * @param callable(string): void $processor Function that receives the extracted file path
 */
function extractAndProcess(
    BulkImport\Contracts\ArchiveExtractorInterface $extractor,
    string $archivePath,
    string $jsbExtension,
    callable $processor,
): bool {
    $tmpDir = sys_get_temp_dir() . '/ibl5_import_' . bin2hex(random_bytes(8));
    if (!mkdir($tmpDir, 0700, true)) {
        echo "        ERROR: Could not create temp directory\n";
        return false;
    }

    $filename = $extractor->jsbFilename($jsbExtension);
    $extracted = $extractor->extractSingleFile($archivePath, $filename, $tmpDir);

    if ($extracted === false) {
        echo "        {$filename} not found in archive\n";
        rmdir($tmpDir);
        return false;
    }

    try {
        $processor($extracted);
    } finally {
        $extractor->cleanupTemp($extracted);
        if (is_dir($tmpDir)) {
            rmdir($tmpDir);
        }
    }

    return true;
}

// ── Process each snapshot ───────────────────────────────────────────────────

foreach ($plan as $si => $entry) {
    $sNum = $si + 1;
    echo "\n" . str_repeat('=', 60) . "\n";
    echo sprintf(
        "[%d/%d] Season %s (%s) from %s\n",
        $sNum,
        count($plan),
        $entry['season'],
        $entry['phase'],
        basename($entry['archive']),
    );
    echo str_repeat('=', 60) . "\n";

    $archivePath = $entry['archive'];
    $endingYear = $entry['ending_year'];
    $phase = $entry['phase'];
    $sourceLabel = basename($archivePath, '.' . pathinfo($archivePath, PATHINFO_EXTENSION));

    extractAndProcess($extractor, $archivePath, 'plr', function (string $path) use (
        $plrService,
        $endingYear,
        $phase,
        $sourceLabel,
        &$totalSnapshots,
        &$totalErrors,
        &$filesProcessed,
    ): void {
        try {
            $result = $plrService->processPlrFileForYear(
                $path,
                $endingYear,
                PlrParser\PlrImportMode::Snapshot,
                $phase,
                $sourceLabel,
            );

            echo "        {$result->summary()}\n";
            $totalSnapshots += $result->playersUpserted;
            $filesProcessed++;
        } catch (\Throwable $e) {
            echo "        ERROR: {$e->getMessage()}\n";
            $totalErrors++;
        }
    });
}

// ── Final summary ───────────────────────────────────────────────────────────
echo "\n" . str_repeat('=', 60) . "\n";
echo "BULK PLR SNAPSHOT IMPORT COMPLETE\n";
echo str_repeat('=', 60) . "\n";
echo sprintf("Archives processed: %d\n", $filesProcessed);
echo sprintf("Snapshots upserted: %d\n", $totalSnapshots);
if ($totalErrors > 0) {
    echo sprintf("Errors:             %d\n", $totalErrors);
}
echo str_repeat('=', 60) . "\n";
