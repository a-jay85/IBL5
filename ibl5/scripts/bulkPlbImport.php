<?php

declare(strict_types=1);

/**
 * Bulk PLB (Depth Chart) Import Script
 *
 * Extracts IBL5.plb files from sim archives and IBL5.plr from HEAT-end archives
 * to populate ibl_plb_snapshots with depth chart history across ~580 archives.
 *
 * Two-phase per-season strategy:
 *   1. Extract HEAT-end IBL5.plr → PlrOrdinalMap (for player identity resolution)
 *   2. For each sim archive: extract IBL5.plb → processPlbFile()
 *
 * Usage:
 *   php bulkPlbImport.php                     # All seasons
 *   php bulkPlbImport.php --dry-run           # List archives to process
 *   php bulkPlbImport.php --season=00-01      # Single season only
 */

// ── CLI-only guard ──────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    echo 'This script must be run from the command line.';
    exit(1);
}

// ── Minimal bootstrap ────────────────────────────────────────────────────────
$_SERVER['PHP_SELF'] = 'bulkPlbImport.php';
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

// ── Scan backups directory ──────────────────────────────────────────────────
$backupsDir = dirname(__DIR__) . '/fullLeagueBackups/backups';
if (!is_dir($backupsDir)) {
    echo "Backups directory not found at: {$backupsDir}\n";
    exit(1);
}

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
 *     heat_archive: string|null,
 *     sim_archives: list<array{path: string, seq: int, phase: string, basename: string}>,
 *     season_dir: string
 * }> $plan
 */
$plan = [];
$totalArchives = 0;

foreach ($seasonDirs as $dirPath) {
    $seasonLabel = basename($dirPath);

    if ($seasonFilter !== null && $seasonLabel !== $seasonFilter) {
        continue;
    }

    $endingYear = $extractor->seasonLabelToEndingYear($seasonLabel);
    if ($endingYear === 0) {
        echo "  WARNING: Cannot parse season label '{$seasonLabel}', skipping\n";
        continue;
    }

    $heatArchive = $extractor->findHeatEndArchive($dirPath);

    // Enumerate all sim archives (excluding heat/preseason)
    /** @var list<string>|false $archiveFiles */
    $archiveFiles = glob($dirPath . '/*.{zip,rar}', GLOB_BRACE);
    if ($archiveFiles === false) {
        $archiveFiles = [];
    }

    sort($archiveFiles);

    /** @var list<array{path: string, seq: int, phase: string, basename: string}> $simArchives */
    $simArchives = [];
    foreach ($archiveFiles as $archivePath) {
        $parsed = $extractor->parseArchiveName(basename($archivePath));
        if ($parsed === null) {
            continue;
        }

        // Skip HEAT and preseason archives (reference, not sim snapshots)
        $phase = strtolower($parsed['phase']);
        if (str_contains($phase, 'heat') || str_contains($phase, 'preseason')) {
            continue;
        }

        $simArchives[] = [
            'path' => $archivePath,
            'seq' => $parsed['seq'],
            'phase' => $parsed['phase'],
            'basename' => pathinfo(basename($archivePath), PATHINFO_FILENAME),
        ];
    }

    $totalArchives += count($simArchives);

    $plan[] = [
        'season' => $seasonLabel,
        'ending_year' => $endingYear,
        'heat_archive' => $heatArchive,
        'sim_archives' => $simArchives,
        'season_dir' => $dirPath,
    ];
}

if ($plan === []) {
    echo "No seasons matched the filter.\n";
    exit(1);
}

echo sprintf("Found %d seasons, %d sim archives to process\n\n", count($plan), $totalArchives);

// ── Dry run ─────────────────────────────────────────────────────────────────
if ($dryRun) {
    echo str_pad('Season', 10) . str_pad('Year', 8)
        . str_pad('HEAT Archive', 45) . "Sim Archives\n";
    echo str_repeat('-', 110) . "\n";

    foreach ($plan as $entry) {
        echo str_pad($entry['season'], 10)
            . str_pad((string) $entry['ending_year'], 8)
            . str_pad(
                $entry['heat_archive'] !== null ? basename($entry['heat_archive']) : '(none)',
                45
            )
            . count($entry['sim_archives']) . " archives\n";
    }

    echo sprintf("\nTotal: %d seasons, %d sim archives.\n", count($plan), $totalArchives);
    exit(0);
}

// ── Initialize services ─────────────────────────────────────────────────────
$repository = new JsbParser\JsbImportRepository($mysqli_db);
$resolver = new JsbParser\PlayerIdResolver($mysqli_db);
$jsbService = new JsbParser\JsbImportService($repository, $resolver);

$totalInserted = 0;
$totalUpdated = 0;
$totalSkipped = 0;
$totalErrors = 0;
$filesProcessed = 0;

// ── Process each season ─────────────────────────────────────────────────────
foreach ($plan as $si => $season) {
    $sNum = $si + 1;
    echo "\n" . str_repeat('=', 60) . "\n";
    echo sprintf(
        "[%d/%d] Season %s (ending year %d)\n",
        $sNum,
        count($plan),
        $season['season'],
        $season['ending_year']
    );
    echo str_repeat('=', 60) . "\n";

    $year = $season['ending_year'];

    // Phase 1: Build PlrOrdinalMap from HEAT-end archive
    $map = JsbParser\PlrOrdinalMap::empty();

    if ($season['heat_archive'] !== null) {
        $tmpDir = sys_get_temp_dir() . '/ibl5_plb_' . bin2hex(random_bytes(8));
        if (mkdir($tmpDir, 0700, true)) {
            $plrPath = $extractor->extractSingleFile($season['heat_archive'], $extractor->jsbFilename('plr'), $tmpDir);
            if ($plrPath !== false) {
                try {
                    $map = JsbParser\PlrOrdinalMap::fromPlrFile($plrPath);
                    echo sprintf("  PLR ordinal map: %d players from %s\n", $map->count(), basename($season['heat_archive']));
                } catch (\Throwable $e) {
                    echo "  WARNING: PLR parse failed: {$e->getMessage()}\n";
                }
                $extractor->cleanupTemp($plrPath);
            } else {
                echo "  WARNING: IBL5.plr not found in HEAT archive\n";
            }
            if (is_dir($tmpDir)) {
                rmdir($tmpDir);
            }
        }
    } else {
        echo "  WARNING: No HEAT archive found — PIDs will be NULL\n";
    }

    // Phase 2: Process each sim archive's .plb
    foreach ($season['sim_archives'] as $ai => $archive) {
        echo sprintf("  [%d/%d] %s\n", $ai + 1, count($season['sim_archives']), basename($archive['path']));

        $tmpDir = sys_get_temp_dir() . '/ibl5_plb_' . bin2hex(random_bytes(8));
        if (!mkdir($tmpDir, 0700, true)) {
            echo "        ERROR: Could not create temp directory\n";
            $totalErrors++;
            continue;
        }

        $plbPath = $extractor->extractSingleFile($archive['path'], $extractor->jsbFilename('plb'), $tmpDir);
        if ($plbPath === false) {
            echo "        IBL5.plb not found in archive\n";
            if (is_dir($tmpDir)) {
                rmdir($tmpDir);
            }
            continue;
        }

        try {
            $result = $jsbService->processPlbFile(
                $plbPath,
                $map,
                $year,
                $archive['seq'],
                $archive['basename'],
            );

            echo "        {$result->summary()}\n";

            $totalInserted += $result->inserted;
            $totalUpdated += $result->updated;
            $totalSkipped += $result->skipped;
            $totalErrors += $result->errors;
            $filesProcessed++;
        } catch (\Throwable $e) {
            echo "        ERROR: {$e->getMessage()}\n";
            $totalErrors++;
        }

        $extractor->cleanupTemp($plbPath);
        if (is_dir($tmpDir)) {
            rmdir($tmpDir);
        }
    }
}

// ── Final summary ───────────────────────────────────────────────────────────
echo "\n" . str_repeat('=', 60) . "\n";
echo "BULK PLB (DEPTH CHART) IMPORT COMPLETE\n";
echo str_repeat('=', 60) . "\n";
echo sprintf("Seasons processed: %d\n", count($plan));
echo sprintf("Files processed:   %d\n", $filesProcessed);
echo sprintf("Records inserted:  %d\n", $totalInserted);
echo sprintf("Records updated:   %d\n", $totalUpdated);
echo sprintf("Records skipped:   %d\n", $totalSkipped);
if ($totalErrors > 0) {
    echo sprintf("Errors:            %d\n", $totalErrors);
}
echo str_repeat('=', 60) . "\n";
