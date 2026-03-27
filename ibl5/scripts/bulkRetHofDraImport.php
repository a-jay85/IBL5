<?php

declare(strict_types=1);

/**
 * Bulk Draft / Retired / Hall of Fame Import Script
 *
 * - .dra and .hof: parsed from IBL0607HEATend (cumulative files)
 * - .ret: parsed from every fullLeagueBackups/ archive (per-season files)
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

require_once __DIR__ . '/../vendor/autoload.php';
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

// ── Directory scanning ──────────────────────────────────────────────────────

$backupsDir = dirname(__DIR__) . '/fullLeagueBackups';
if (!is_dir($backupsDir)) {
    echo "fullLeagueBackups/ directory not found at: {$backupsDir}\n";
    exit(1);
}

$cumulativeDir = $backupsDir . '/IBL0607HEATend';

/**
 * Extract season ending year and phase from a directory name.
 *
 * @return array{year: int|null, phase: string|null}
 */
function parseFolderName(string $name): array
{
    $year = null;
    $phase = null;

    if (preg_match('/(\d{2})(\d{2})/', $name, $yearMatch) === 1) {
        $endPart = (int) $yearMatch[2];
        $year = $endPart >= 50 ? 1900 + $endPart : 2000 + $endPart;
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

/**
 * Scan fullLeagueBackups/ for archives with .ret files, deduplicated to one per season.
 *
 * @return list<array{path: string, dir: string, year: int}>
 */
function findRetArchives(string $backupsDir): array
{
    /** @var list<string> $dirs */
    $dirs = glob($backupsDir . '/*', GLOB_ONLYDIR);
    if ($dirs === false || $dirs === []) {
        return [];
    }

    /** @var list<array{path: string, dir: string, year: int, phase: string}> $entries */
    $entries = [];

    foreach ($dirs as $dirPath) {
        $dirName = basename($dirPath);
        $parsed = parseFolderName($dirName);

        if ($parsed['year'] === null || $parsed['phase'] === null) {
            continue;
        }

        if (!file_exists($dirPath . '/IBL5.ret')) {
            continue;
        }

        $entries[] = [
            'path' => $dirPath,
            'dir' => $dirName,
            'year' => $parsed['year'],
            'phase' => $parsed['phase'],
        ];
    }

    // Sort: by year ascending, then prefer Finals/Season-end over HEAT
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

    return array_values(array_map(
        static fn (array $e): array => ['path' => $e['path'], 'dir' => $e['dir'], 'year' => $e['year']],
        $deduped,
    ));
}

$fileTypes = $fileTypeFilter !== null ? [$fileTypeFilter] : ['dra', 'ret', 'hof'];

// ── Dry run: list files and exit ────────────────────────────────────────────
if ($dryRun) {
    foreach ($fileTypes as $type) {
        if ($type === 'ret') {
            $retArchives = findRetArchives($backupsDir);
            echo ".ret archives (" . count($retArchives) . " seasons):\n";
            echo str_pad('Directory', 35) . str_pad('Year', 8) . "File\n";
            echo str_repeat('-', 60) . "\n";
            foreach ($retArchives as $archive) {
                $exists = file_exists($archive['path'] . '/IBL5.ret') ? 'Y' : '-';
                echo str_pad($archive['dir'], 35) . str_pad((string) $archive['year'], 8) . $exists . "\n";
            }
            echo "\n";
        } else {
            $path = $cumulativeDir . '/IBL5.' . $type;
            $exists = file_exists($path) ? 'Y' : '-';
            echo ".{$type}: {$path} [{$exists}]\n";
        }
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
    if ($fileType === 'ret') {
        // .ret: iterate all archives (per-season)
        $retArchives = findRetArchives($backupsDir);
        echo sprintf("\nProcessing .ret files (%d seasons)\n", count($retArchives));
        echo str_repeat('-', 50) . "\n";

        foreach ($retArchives as $archive) {
            $filePath = $archive['path'] . '/IBL5.ret';
            echo sprintf("  [%d] %s\n", $archive['year'], $archive['dir']);

            try {
                $result = $service->processRetFile($filePath, $archive['year']);
                echo "       {$result->summary()}\n";

                $totalInserted += $result->inserted;
                $totalUpdated += $result->updated;
                $totalSkipped += $result->skipped;
                $totalErrors += $result->errors;
                $filesProcessed++;
            } catch (\Throwable $e) {
                echo "       ERROR: {$e->getMessage()}\n";
                $totalErrors++;
            }
        }
    } else {
        // .dra and .hof: single cumulative file from IBL0607HEATend
        $filePath = $cumulativeDir . '/IBL5.' . $fileType;
        if (!file_exists($filePath)) {
            echo "Skipping .{$fileType}: file not found at {$filePath}\n";
            continue;
        }

        echo sprintf("\nProcessing .%s — %s\n", $fileType, $filePath);

        try {
            $result = match ($fileType) {
                'dra' => $service->processDraFile($filePath),
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
