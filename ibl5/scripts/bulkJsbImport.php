<?php

declare(strict_types=1);

/**
 * Bulk JSB File Import Script
 *
 * Scans fullLeagueBackups/ directories for JSB engine files (.car, .his, .trn, .asw)
 * and processes them through JsbImportService to populate database tables.
 *
 * Import order: .trn → .car → .his → .asw
 * (Trade data from .trn helps PlayerIdResolver handle mid-season moves in .car)
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
$validTypes = ['car', 'his', 'trn', 'asw'];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--file-type=')) {
        $fileTypeFilter = substr($arg, strlen('--file-type='));
        if (!in_array($fileTypeFilter, $validTypes, true)) {
            echo "Invalid file type: {$fileTypeFilter}. Valid types: " . implode(', ', $validTypes) . "\n";
            exit(1);
        }
    }
}

// ── Scan for JSB files ─────────────────────────────────────────────────────
$backupsDir = dirname(__DIR__) . '/fullLeagueBackups';
if (!is_dir($backupsDir)) {
    echo "fullLeagueBackups/ directory not found at: {$backupsDir}\n";
    exit(1);
}

/** @var list<string> $dirs */
$dirs = glob($backupsDir . '/*', GLOB_ONLYDIR);
if ($dirs === false || $dirs === []) {
    echo "No directories found in fullLeagueBackups/\n";
    exit(1);
}

echo sprintf("Found %d directories in fullLeagueBackups/\n\n", count($dirs));

// ── Parse directory names for season metadata ───────────────────────────────

/**
 * Extract season ending year and phase from a directory name.
 *
 * Replicates the parseFolderName() logic from bulkScoImport.php.
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
 * @var list<array{path: string, dir: string, year: int, phase: string}> $entries
 */
$entries = [];
$parseErrors = [];

foreach ($dirs as $dirPath) {
    $dirName = basename($dirPath);
    $parsed = parseFolderName($dirName);

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
    foreach (['IBL5.car', 'IBL5.his', 'IBL5.trn', 'IBL5.asw'] as $jsbFile) {
        if (file_exists($dirPath . '/' . $jsbFile)) {
            $hasJsbFiles = true;
            break;
        }
    }

    if (!$hasJsbFiles) {
        continue;
    }

    $entries[] = [
        'path' => $dirPath,
        'dir' => $dirName,
        'year' => $parsed['year'],
        'phase' => $parsed['phase'],
    ];
}

// ── Sort: by year ascending, then prefer Finals/Season-end over HEAT ────────
usort($entries, static function (array $a, array $b): int {
    if ($a['year'] !== $b['year']) {
        return $a['year'] <=> $b['year'];
    }

    $phaseOrder = ['Preseason' => 0, 'HEAT' => 1, 'Regular Season/Playoffs' => 2];
    $aOrder = $phaseOrder[$a['phase']] ?? 9;
    $bOrder = $phaseOrder[$b['phase']] ?? 9;

    return $aOrder <=> $bOrder;
});

// ── Deduplicate: prefer season-end snapshot per year ────────────────────────
$deduped = [];
foreach ($entries as $entry) {
    $year = $entry['year'];
    // Later phases override earlier ones (Playoffs > HEAT > Preseason)
    $deduped[$year] = $entry;
}
/** @var list<array{path: string, dir: string, year: int, phase: string}> $entries */
$entries = array_values($deduped);

// ── Output parse errors ─────────────────────────────────────────────────────
if ($parseErrors !== []) {
    foreach ($parseErrors as $error) {
        echo $error . "\n";
    }
    echo "\n";
}

// ── Dry run: list files and exit ────────────────────────────────────────────
if ($dryRun) {
    echo str_pad('Directory', 40) . str_pad('Year', 8) . str_pad('Phase', 25);
    echo str_pad('.car', 5) . str_pad('.his', 5) . str_pad('.trn', 5) . ".asw\n";
    echo str_repeat('-', 90) . "\n";

    foreach ($entries as $entry) {
        $car = file_exists($entry['path'] . '/IBL5.car') ? 'Y' : '-';
        $his = file_exists($entry['path'] . '/IBL5.his') ? 'Y' : '-';
        $trn = file_exists($entry['path'] . '/IBL5.trn') ? 'Y' : '-';
        $asw = file_exists($entry['path'] . '/IBL5.asw') ? 'Y' : '-';

        echo str_pad($entry['dir'], 40)
            . str_pad((string) $entry['year'], 8)
            . str_pad($entry['phase'], 25)
            . str_pad($car, 5) . str_pad($his, 5) . str_pad($trn, 5) . $asw . "\n";
    }

    echo sprintf("\nTotal: %d directories ready for processing.\n", count($entries));
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

$fileTypes = $fileTypeFilter !== null ? [$fileTypeFilter] : ['trn', 'car', 'his', 'asw'];

foreach ($fileTypes as $fileType) {
    echo "\n" . str_repeat('=', 50) . "\n";
    echo "Processing .{$fileType} files\n";
    echo str_repeat('=', 50) . "\n\n";

    foreach ($entries as $i => $entry) {
        $num = $i + 1;
        $filePath = $entry['path'] . '/IBL5.' . $fileType;

        if (!file_exists($filePath)) {
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
