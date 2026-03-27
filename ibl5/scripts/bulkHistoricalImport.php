<?php

declare(strict_types=1);

/**
 * Bulk Historical Import Script
 *
 * Extracts targeted files from the backups/{season}/ zip archives and processes
 * them through existing parsers to populate ibl_hist, ibl_box_scores,
 * ibl_jsb_history, ibl_jsb_transactions, etc.
 *
 * Unlike bulkJsbImport.php (which reads pre-extracted directories), this script
 * works directly with the archived zip files using ArchiveExtractor.
 *
 * Archive selection logic:
 *   - .car/.his/.trn/.asw/.rcb: last archive per season (cumulative within season)
 *   - .sco: HEAT-end AND finals archive (HEAT box scores separate from regular season)
 *
 * Import order per season: .trn → .car → .his → .sco(heat) → .sco(finals) → .asw → .rcb
 *
 * Usage:
 *   php bulkHistoricalImport.php                     # All types, all seasons
 *   php bulkHistoricalImport.php --dry-run           # List archives to process
 *   php bulkHistoricalImport.php --file-type=car     # Single type only
 *   php bulkHistoricalImport.php --season=00-01      # Single season only
 */

// ── CLI-only guard ──────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    echo 'This script must be run from the command line.';
    exit(1);
}

// ── Minimal bootstrap ────────────────────────────────────────────────────────
$_SERVER['PHP_SELF'] = 'bulkHistoricalImport.php';
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

$validTypes = ['car', 'his', 'trn', 'asw', 'awa', 'rcb', 'sco'];
$fileTypeFilter = null;
$seasonFilter = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--file-type=')) {
        $fileTypeFilter = substr($arg, strlen('--file-type='));
        if (!in_array($fileTypeFilter, $validTypes, true)) {
            echo "Invalid file type: {$fileTypeFilter}. Valid types: " . implode(', ', $validTypes) . "\n";
            exit(1);
        }
    }
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
 *     finals_archive: string|null,
 *     heat_archive: string|null,
 *     season_dir: string
 * }> $plan
 */
$plan = [];

foreach ($seasonDirs as $dirPath) {
    $seasonLabel = basename($dirPath);

    // Apply season filter
    if ($seasonFilter !== null && $seasonLabel !== $seasonFilter) {
        continue;
    }

    $endingYear = $extractor->seasonLabelToEndingYear($seasonLabel);
    if ($endingYear === 0) {
        echo "  WARNING: Cannot parse season label '{$seasonLabel}', skipping\n";
        continue;
    }

    $finalsArchive = $extractor->findLastArchive($dirPath);
    $heatArchive = $extractor->findHeatEndArchive($dirPath);

    $plan[] = [
        'season' => $seasonLabel,
        'ending_year' => $endingYear,
        'finals_archive' => $finalsArchive,
        'heat_archive' => $heatArchive,
        'season_dir' => $dirPath,
    ];
}

if ($plan === []) {
    echo "No seasons matched the filter.\n";
    exit(1);
}

echo sprintf("Found %d seasons to process\n\n", count($plan));

// ── Dry run ─────────────────────────────────────────────────────────────────
if ($dryRun) {
    echo str_pad('Season', 10) . str_pad('Year', 8)
        . str_pad('Finals Archive', 45) . "HEAT Archive\n";
    echo str_repeat('-', 110) . "\n";

    foreach ($plan as $entry) {
        echo str_pad($entry['season'], 10)
            . str_pad((string) $entry['ending_year'], 8)
            . str_pad(
                $entry['finals_archive'] !== null ? basename($entry['finals_archive']) : '(none)',
                45
            )
            . ($entry['heat_archive'] !== null ? basename($entry['heat_archive']) : '(none)')
            . "\n";
    }

    echo sprintf("\nTotal: %d seasons, up to %d archives to process.\n", count($plan), count($plan) * 2);
    echo "\nFile types to import: " . ($fileTypeFilter ?? implode(', ', $validTypes)) . "\n";
    exit(0);
}

// ── Initialize services ─────────────────────────────────────────────────────
$repository = new JsbParser\JsbImportRepository($mysqli_db);
$resolver = new JsbParser\PlayerIdResolver($mysqli_db);
$jsbService = new JsbParser\JsbImportService($repository, $resolver);
$boxscoreProcessor = new Boxscore\BoxscoreProcessor($mysqli_db);

$totalInserted = 0;
$totalUpdated = 0;
$totalSkipped = 0;
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

// ── Process each season ─────────────────────────────────────────────────────

$fileTypes = $fileTypeFilter !== null ? [$fileTypeFilter] : ['trn', 'car', 'his', 'sco', 'asw', 'awa', 'rcb'];

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

    if ($season['finals_archive'] === null) {
        echo "  WARNING: No finals archive found, skipping season\n";
        continue;
    }

    $finalsArchive = $season['finals_archive'];
    $heatArchive = $season['heat_archive'];
    $year = $season['ending_year'];
    $sourceLabel = basename($finalsArchive, '.' . pathinfo($finalsArchive, PATHINFO_EXTENSION));

    foreach ($fileTypes as $fileType) {
        // .sco needs both HEAT-end and finals archives
        if ($fileType === 'sco') {
            // HEAT .sco
            if ($heatArchive !== null) {
                echo "  .sco (HEAT) from " . basename($heatArchive) . "\n";
                extractAndProcess($extractor, $heatArchive, 'sco', function (string $path) use (
                    $boxscoreProcessor,
                    $year,
                    &$totalInserted,
                    &$totalUpdated,
                    &$totalSkipped,
                    &$totalErrors,
                    &$filesProcessed,
                ): void {
                    try {
                        $result = $boxscoreProcessor->processScoFile($path, $year, 'HEAT', skipSimDates: true);
                        $inserted = (int) $result['gamesInserted'];
                        $updated = (int) $result['gamesUpdated'];
                        $skipped = (int) $result['gamesSkipped'];
                        echo sprintf("        Games: %d inserted, %d updated, %d skipped\n", $inserted, $updated, $skipped);
                        $totalInserted += $inserted;
                        $totalUpdated += $updated;
                        $totalSkipped += $skipped;
                        $filesProcessed++;
                    } catch (\Throwable $e) {
                        echo "        ERROR: {$e->getMessage()}\n";
                        $totalErrors++;
                    }
                });
            }

            // Finals .sco
            echo "  .sco (finals) from " . basename($finalsArchive) . "\n";
            extractAndProcess($extractor, $finalsArchive, 'sco', function (string $path) use (
                $boxscoreProcessor,
                $year,
                &$totalInserted,
                &$totalUpdated,
                &$totalSkipped,
                &$totalErrors,
                &$filesProcessed,
            ): void {
                try {
                    $result = $boxscoreProcessor->processScoFile($path, $year, 'Regular Season/Playoffs', skipSimDates: true);
                    $inserted = (int) $result['gamesInserted'];
                    $updated = (int) $result['gamesUpdated'];
                    $skipped = (int) $result['gamesSkipped'];
                    echo sprintf("        Games: %d inserted, %d updated, %d skipped\n", $inserted, $updated, $skipped);
                    $totalInserted += $inserted;
                    $totalUpdated += $updated;
                    $totalSkipped += $skipped;
                    $filesProcessed++;
                } catch (\Throwable $e) {
                    echo "        ERROR: {$e->getMessage()}\n";
                    $totalErrors++;
                }
            });

            // Also process All-Star games from the finals archive
            extractAndProcess($extractor, $finalsArchive, 'sco', function (string $path) use (
                $boxscoreProcessor,
                $year,
                &$totalInserted,
                &$totalErrors,
            ): void {
                try {
                    $result = $boxscoreProcessor->processAllStarGames($path, $year);
                    $asInserted = (int) ($result['gamesInserted'] ?? 0);
                    if ($asInserted > 0) {
                        echo sprintf("        All-Star Games: %d inserted\n", $asInserted);
                        $totalInserted += $asInserted;
                    }
                } catch (\Throwable $e) {
                    echo "        ERROR (All-Star): {$e->getMessage()}\n";
                    $totalErrors++;
                }
            });

            continue;
        }

        // .awa needs both .awa and .car files from the same archive
        if ($fileType === 'awa') {
            echo "  .awa from " . basename($finalsArchive) . "\n";
            $tmpDir = sys_get_temp_dir() . '/ibl5_import_' . bin2hex(random_bytes(8));
            if (!mkdir($tmpDir, 0700, true)) {
                echo "        ERROR: Could not create temp directory\n";
                continue;
            }

            $awaPath = $extractor->extractSingleFile($finalsArchive, $extractor->jsbFilename('awa'), $tmpDir);
            $carPath = $extractor->extractSingleFile($finalsArchive, $extractor->jsbFilename('car'), $tmpDir);

            if ($awaPath === false) {
                echo "        IBL5.awa not found in archive\n";
            } elseif ($carPath === false) {
                echo "        IBL5.car not found in archive (needed for .awa PID resolution)\n";
                $extractor->cleanupTemp($awaPath);
            } else {
                try {
                    $result = $jsbService->processAwaFile($awaPath, $carPath);
                    echo "        {$result->summary()}\n";
                    foreach ($result->messages as $msg) {
                        echo "        {$msg}\n";
                    }
                    $totalInserted += $result->inserted;
                    $totalUpdated += $result->updated;
                    $totalSkipped += $result->skipped;
                    $totalErrors += $result->errors;
                    $filesProcessed++;
                } catch (\Throwable $e) {
                    echo "        ERROR: {$e->getMessage()}\n";
                    $totalErrors++;
                }
                $extractor->cleanupTemp($awaPath);
                $extractor->cleanupTemp($carPath);
            }

            if (is_dir($tmpDir)) {
                rmdir($tmpDir);
            }
            continue;
        }

        // All other types use the finals archive only
        echo "  .{$fileType} from " . basename($finalsArchive) . "\n";
        extractAndProcess($extractor, $finalsArchive, $fileType, function (string $path) use (
            $jsbService,
            $fileType,
            $year,
            $sourceLabel,
            &$totalInserted,
            &$totalUpdated,
            &$totalSkipped,
            &$totalErrors,
            &$filesProcessed,
        ): void {
            try {
                $result = match ($fileType) {
                    'car' => $jsbService->processCarFile($path, null),
                    'his' => $jsbService->processHisFile($path, $sourceLabel),
                    'trn' => $jsbService->processTrnFile($path, $sourceLabel),
                    'asw' => $jsbService->processAswFile($path, $year),
                    'rcb' => $jsbService->processRcbFile($path, $year, $sourceLabel),
                    default => throw new \RuntimeException("Unknown file type: {$fileType}"),
                };

                echo "        {$result->summary()}\n";
                foreach ($result->messages as $msg) {
                    echo "        {$msg}\n";
                }

                $totalInserted += $result->inserted;
                $totalUpdated += $result->updated;
                $totalSkipped += $result->skipped;
                $totalErrors += $result->errors;
                $filesProcessed++;
            } catch (\Throwable $e) {
                echo "        ERROR: {$e->getMessage()}\n";
                $totalErrors++;
            }
        });
    }

    // Clear resolver cache between seasons to avoid stale lookups
    $resolver->clearCache();
}

// ── Final summary ───────────────────────────────────────────────────────────
echo "\n" . str_repeat('=', 60) . "\n";
echo "BULK HISTORICAL IMPORT COMPLETE\n";
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
