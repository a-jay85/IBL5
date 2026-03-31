<?php

declare(strict_types=1);

/**
 * Bulk JSB File Import Script
 *
 * Scans for JSB engine files (.car, .his, .trn, .asw, .awa) and processes
 * them through JsbImportService to populate database tables.
 *
 * Import order: .trn → .car → .his → .asw → .awa
 * (Trade data from .trn helps PlayerIdResolver handle mid-season moves in .car)
 *
 * Environment auto-detection:
 *   - fullLeagueBackups/ exists → local mode (pre-extracted directories)
 *   - backups/ exists            → production mode (zip archives via ArchiveExtractor)
 *
 * Usage:
 *   php bulkJsbImport.php                     # Full processing mode
 *   php bulkJsbImport.php --dry-run           # List files with detected metadata only
 *   php bulkJsbImport.php --file-type=car     # Only process .car files
 *   php bulkJsbImport.php --file-type=his     # Only process .his files
 *   php bulkJsbImport.php --file-type=trn     # Only process .trn files
 *   php bulkJsbImport.php --file-type=asw     # Only process .asw files
 */

// ── CLI-only guard ──────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    echo 'This script must be run from the command line.';
    exit(1);
}

// ── Minimal bootstrap (avoids web-only mainfile.php) ────────────────────────
$_SERVER['PHP_SELF'] = 'bulkJsbImport.php';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require_once __DIR__ . '/../autoloader.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/db.php';

/** @var \mysqli $mysqli_db */

// ── Parse CLI options ───────────────────────────────────────────────────────
$dryRun = in_array('--dry-run', $argv, true);

$fileTypeFilter = null;
$validTypes = ['car', 'his', 'trn', 'asw', 'awa'];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--file-type=')) {
        $fileTypeFilter = substr($arg, strlen('--file-type='));
        if (!in_array($fileTypeFilter, $validTypes, true)) {
            echo "Invalid file type: {$fileTypeFilter}. Valid types: " . implode(', ', $validTypes) . "\n";
            exit(1);
        }
    }
}

// ── Environment auto-detection ──────────────────────────────────────────────
$extractor = new BulkImport\ArchiveExtractor();

$localDir = dirname(__DIR__) . '/fullLeagueBackups';
$prodDir  = dirname(__DIR__) . '/backups';

if (is_dir($localDir)) {
    $isProduction = false;
    $backupsDir   = $localDir;
} elseif (is_dir($prodDir)) {
    $isProduction = true;
    $backupsDir   = $prodDir;
} else {
    echo "No backup directory found (expected fullLeagueBackups/ or backups/).\n";
    exit(1);
}

echo sprintf(
    "Mode: %s (source: %s)\n",
    $isProduction ? 'production' : 'local',
    $backupsDir
);

// ── Build entries ───────────────────────────────────────────────────────────

/**
 * @var list<array{path: string, dir: string, year: int, phase: string}> $entries
 */
$entries = [];
$parseErrors = [];

if ($isProduction) {
    // ── Production: season subdirectories containing zip archives ──────────
    /** @var list<string>|false $seasonDirs */
    $seasonDirs = glob($backupsDir . '/*', GLOB_ONLYDIR);
    if ($seasonDirs === false || $seasonDirs === []) {
        echo "No season directories found in backups/\n";
        exit(1);
    }
    sort($seasonDirs);

    foreach ($seasonDirs as $dirPath) {
        $label = basename($dirPath);
        $year  = $extractor->seasonLabelToEndingYear($label);
        if ($year === 0) {
            $parseErrors[] = "  WARNING: Cannot parse season label '{$label}', skipping";
            continue;
        }
        if ($extractor->findLastArchive($dirPath) === null) {
            continue; // no zip/rar archives in this directory
        }
        $entries[] = [
            'path'  => $dirPath,
            'dir'   => $label,
            'year'  => $year,
            'phase' => 'Regular Season/Playoffs',
        ];
    }

    // Sort by year ascending (one dir per season — no dedup needed)
    usort($entries, static function (array $a, array $b): int {
        return $a['year'] <=> $b['year'];
    });
} else {
    // ── Local: pre-extracted directories in fullLeagueBackups/ ─────────────

    /** @var list<string>|false $dirs */
    $dirs = glob($backupsDir . '/*', GLOB_ONLYDIR);
    if ($dirs === false || $dirs === []) {
        echo "No directories found in fullLeagueBackups/\n";
        exit(1);
    }

    echo sprintf("Found %d directories in fullLeagueBackups/\n\n", count($dirs));

    foreach ($dirs as $dirPath) {
        $dirName = basename($dirPath);
        $parsed  = parseFolderName($dirName);

        if ($parsed['year'] === null || $parsed['phase'] === null) {
            $parseErrors[] = sprintf(
                "  WARNING: Could not detect metadata for %s (year=%s, phase=%s)",
                $dirName,
                $parsed['year'] !== null ? (string) $parsed['year'] : 'null',
                $parsed['phase'] ?? 'null'
            );
            continue;
        }

        // Check for JSB files
        $hasJsbFiles = false;
        foreach (['IBL5.car', 'IBL5.his', 'IBL5.trn', 'IBL5.asw', 'IBL5.awa'] as $jsbFile) {
            if (file_exists($dirPath . '/' . $jsbFile)) {
                $hasJsbFiles = true;
                break;
            }
        }

        if (!$hasJsbFiles) {
            continue;
        }

        $entries[] = [
            'path'  => $dirPath,
            'dir'   => $dirName,
            'year'  => $parsed['year'],
            'phase' => $parsed['phase'],
        ];
    }

    // Sort: by year ascending, then by phase priority
    usort($entries, static function (array $a, array $b): int {
        if ($a['year'] !== $b['year']) {
            return $a['year'] <=> $b['year'];
        }

        $phaseOrder = ['Preseason' => 0, 'HEAT' => 1, 'Regular Season/Playoffs' => 2];
        $aOrder = $phaseOrder[$a['phase']] ?? 9;
        $bOrder = $phaseOrder[$b['phase']] ?? 9;

        return $aOrder <=> $bOrder;
    });

    // Deduplicate: prefer season-end snapshot per year
    $deduped = [];
    foreach ($entries as $entry) {
        $deduped[$entry['year']] = $entry;
    }
    /** @var list<array{path: string, dir: string, year: int, phase: string}> $entries */
    $entries = array_values($deduped);
}

// ── Output parse errors ─────────────────────────────────────────────────────
if ($parseErrors !== []) {
    foreach ($parseErrors as $error) {
        echo $error . "\n";
    }
    echo "\n";
}

// ── Dry run: list files and exit ────────────────────────────────────────────
if ($dryRun) {
    echo str_pad('Directory/Season', 40) . str_pad('Year', 8) . str_pad('Phase', 25);
    echo str_pad('.car', 5) . str_pad('.his', 5) . str_pad('.trn', 5) . str_pad('.asw', 5) . ".awa\n";
    echo str_repeat('-', 95) . "\n";

    foreach ($entries as $entry) {
        if ($isProduction) {
            $archive = $extractor->findLastArchive($entry['path']);
            $car = $his = $trn = $asw = $awa = '-';
            if ($archive !== null) {
                $zip = new ZipArchive();
                if ($zip->open($archive) === true) {
                    $car = $zip->locateName('IBL5.car') !== false ? 'Y' : '-';
                    $his = $zip->locateName('IBL5.his') !== false ? 'Y' : '-';
                    $trn = $zip->locateName('IBL5.trn') !== false ? 'Y' : '-';
                    $asw = $zip->locateName('IBL5.asw') !== false ? 'Y' : '-';
                    $awa = $zip->locateName('IBL5.awa') !== false ? 'Y' : '-';
                    $zip->close();
                }
            }
        } else {
            $car = file_exists($entry['path'] . '/IBL5.car') ? 'Y' : '-';
            $his = file_exists($entry['path'] . '/IBL5.his') ? 'Y' : '-';
            $trn = file_exists($entry['path'] . '/IBL5.trn') ? 'Y' : '-';
            $asw = file_exists($entry['path'] . '/IBL5.asw') ? 'Y' : '-';
            $awa = file_exists($entry['path'] . '/IBL5.awa') ? 'Y' : '-';
        }

        echo str_pad($entry['dir'], 40)
            . str_pad((string) $entry['year'], 8)
            . str_pad($entry['phase'], 25)
            . str_pad($car, 5) . str_pad($his, 5) . str_pad($trn, 5) . str_pad($asw, 5) . $awa . "\n";
    }

    echo sprintf("\nTotal: %d directories ready for processing.\n", count($entries));
    exit(0);
}

// ── File path resolution closures ──────────────────────────────────────────
// getFilePath: returns the path to an extracted/existing JSB file, or null.
// cleanupFile: removes temp files/dirs created by getFilePath (no-op for local).
if ($isProduction) {
    $getFilePath = static function (string $dirPath, string $fileType) use ($extractor): ?string {
        $archive = $extractor->findLastArchive($dirPath);
        if ($archive === null) {
            return null;
        }
        $tmpDir = sys_get_temp_dir() . '/ibl5_jsb_' . bin2hex(random_bytes(8));
        if (!mkdir($tmpDir, 0700, true)) {
            echo "        ERROR: Could not create temp directory\n";
            return null;
        }
        $extracted = $extractor->extractSingleFile($archive, 'IBL5.' . $fileType, $tmpDir);
        if ($extracted === false) {
            rmdir($tmpDir);
            return null;
        }
        // .awa processing also needs a companion .car for PID→name resolution.
        // Extract it into the same temp dir so dirname($awaPath) . '/IBL5.car' resolves.
        if ($fileType === 'awa') {
            $extractor->extractSingleFile($archive, 'IBL5.car', $tmpDir);
        }
        return $extracted;
    };

    $cleanupFile = static function (?string $path) use ($extractor): void {
        if ($path === null) {
            return;
        }
        $extractor->cleanupTemp($path);
        $tmpDir = dirname($path);
        // Remove companion .car extracted for .awa name resolution (if any)
        $companionCar = $tmpDir . '/IBL5.car';
        if (file_exists($companionCar)) {
            unlink($companionCar);
        }
        if (is_dir($tmpDir)) {
            rmdir($tmpDir);
        }
    };
} else {
    $getFilePath = static function (string $dirPath, string $fileType): ?string {
        $p = $dirPath . '/IBL5.' . $fileType;
        return file_exists($p) ? $p : null;
    };

    $cleanupFile = static function (?string $path): void {
        // no-op: local files are pre-extracted and must not be deleted
    };
}

// ── Process files ───────────────────────────────────────────────────────────
$repository = new JsbParser\JsbImportRepository($mysqli_db);
$resolver   = new JsbParser\PlayerIdResolver($mysqli_db);
$service    = new JsbParser\JsbImportService($repository, $resolver);

$totalInserted = 0;
$totalUpdated  = 0;
$totalSkipped  = 0;
$totalErrors   = 0;
$filesProcessed = 0;

$fileTypes = $fileTypeFilter !== null ? [$fileTypeFilter] : ['trn', 'car', 'his', 'asw', 'awa'];

foreach ($fileTypes as $fileType) {
    echo "\n" . str_repeat('=', 50) . "\n";
    echo "Processing .{$fileType} files\n";
    echo str_repeat('=', 50) . "\n\n";

    foreach ($entries as $i => $entry) {
        $num      = $i + 1;
        $filePath = $getFilePath($entry['path'], $fileType);

        if ($filePath === null) {
            continue;
        }

        echo sprintf(
            "[%d/%d] %s (%d %s) — .%s\n",
            $num,
            count($entries),
            $entry['dir'],
            $entry['year'],
            $entry['phase'],
            $fileType
        );

        $sourceLabel = $entry['dir'];

        try {
            $result = match ($fileType) {
                'car' => $service->processCarFile($filePath, null),
                'his' => $service->processHisFile($filePath, $sourceLabel),
                'trn' => $service->processTrnFile($filePath, $sourceLabel),
                'asw' => $service->processAswFile($filePath, $entry['year']),
                'awa' => $service->processAwaFile($filePath, dirname($filePath) . '/IBL5.car'),
                default => throw new \RuntimeException("Unknown file type: {$fileType}"),
            };

            echo "        {$result->summary()}\n";

            foreach ($result->messages as $msg) {
                echo "        {$msg}\n";
            }

            $totalInserted  += $result->inserted;
            $totalUpdated   += $result->updated;
            $totalSkipped   += $result->skipped;
            $totalErrors    += $result->errors;
            $filesProcessed++;
        } catch (\Throwable $e) {
            echo "        ERROR: {$e->getMessage()}\n";
            $totalErrors++;
        } finally {
            $cleanupFile($filePath);
        }

        // Clear resolver cache between seasons to avoid stale lookups
        $resolver->clearCache();
    }
}

// ── Final summary ───────────────────────────────────────────────────────────
echo "\n" . str_repeat('=', 50) . "\n";
echo "BULK JSB IMPORT COMPLETE\n";
echo str_repeat('=', 50) . "\n";
echo sprintf("Files processed: %d\n", $filesProcessed);
echo sprintf("Records inserted:  %d\n", $totalInserted);
echo sprintf("Records updated:   %d\n", $totalUpdated);
echo sprintf("Records skipped:   %d\n", $totalSkipped);
if ($totalErrors > 0) {
    echo sprintf("Errors:            %d\n", $totalErrors);
}
echo str_repeat('=', 50) . "\n";

// ── Helper functions ────────────────────────────────────────────────────────

/**
 * Extract season ending year and phase from a directory name.
 *
 * @return array{year: int|null, phase: string|null}
 */
function parseFolderName(string $name): array
{
    $year  = null;
    $phase = null;

    if (preg_match('/(\d{2})(\d{2})/', $name, $yearMatch) === 1) {
        $endPart = (int) $yearMatch[2];
        $year    = $endPart >= 50 ? 1900 + $endPart : 2000 + $endPart;
    }

    $lower = strtolower($name);
    if (str_contains($lower, 'preseason')) {
        $phase = 'Preseason';
    } elseif (str_contains($lower, 'heat')) {
        $phase = 'HEAT';
    } elseif (
        str_contains($lower, 'playoff')
        || str_contains($lower, 'finals')
        || str_contains($lower, 'season')
        || preg_match('/sim\d*/i', $name) === 1
    ) {
        $phase = 'Regular Season/Playoffs';
    }

    return ['year' => $year, 'phase' => $phase];
}
