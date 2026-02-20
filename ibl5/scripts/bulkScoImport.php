<?php

declare(strict_types=1);

/**
 * Bulk .sco File Import Script
 *
 * Scans scoFiles subdirectories for historical IBL5.sco files and processes them
 * through BoxscoreProcessor to fill data gaps in ibl_box_scores and
 * ibl_box_scores_teams tables.
 *
 * Usage:
 *   php bulkScoImport.php            # Full processing mode
 *   php bulkScoImport.php --dry-run  # List files with detected metadata only
 */

// ── CLI-only guard ──────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    echo 'This script must be run from the command line.';
    exit(1);
}

// ── Minimal bootstrap (avoids web-only mainfile.php) ────────────────────────
$_SERVER['PHP_SELF'] = 'bulkScoImport.php';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/db.php';

/** @var \mysqli $mysqli_db */

// ── Parse CLI options ───────────────────────────────────────────────────────
$dryRun = in_array('--dry-run', $argv, true);

// ── Scan for .sco files ─────────────────────────────────────────────────────
$scoFilesDir = __DIR__ . '/../scoFiles';
$pattern = $scoFilesDir . '/*/IBL5.sco';
$files = glob($pattern);

if ($files === false || $files === []) {
    echo "No .sco files found matching: {$pattern}\n";
    exit(1);
}

echo sprintf("Found %d .sco files in scoFiles/\n\n", count($files));

// ── Parse directory names for season metadata ───────────────────────────────

/**
 * Extract season ending year and phase from a directory name.
 *
 * Replicates the BoxscoreView JS parseFolderName() logic:
 * - Year: first 4-digit sequence split into two 2-digit parts; last 2 digits >= 50 => 1900+n, < 50 => 2000+n
 * - Phase: keyword priority — preseason > heat > playoff/finals/sim > default
 *
 * @return array{year: int|null, phase: string|null}
 */
function parseFolderName(string $name): array
{
    $year = null;
    $phase = null;

    // Year: first 4-digit sequence split into two 2-digit parts
    if (preg_match('/(\d{2})(\d{2})/', $name, $yearMatch) === 1) {
        $endPart = (int) $yearMatch[2];
        $year = $endPart >= 50 ? 1900 + $endPart : 2000 + $endPart;
    }

    // Phase: keyword search (priority order)
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

foreach ($files as $filePath) {
    $dirName = basename(dirname($filePath));
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

    $entries[] = [
        'path' => $filePath,
        'dir' => $dirName,
        'year' => $parsed['year'],
        'phase' => $parsed['phase'],
    ];
}

// ── Sort: by year ascending, then HEAT before Regular Season/Playoffs ───────
usort($entries, static function (array $a, array $b): int {
    if ($a['year'] !== $b['year']) {
        return $a['year'] <=> $b['year'];
    }

    // HEAT comes before Regular Season/Playoffs within the same season
    $phaseOrder = ['Preseason' => 0, 'HEAT' => 1, 'Regular Season/Playoffs' => 2];
    $aOrder = $phaseOrder[$a['phase']] ?? 9;
    $bOrder = $phaseOrder[$b['phase']] ?? 9;

    return $aOrder <=> $bOrder;
});

// ── Output parse errors ─────────────────────────────────────────────────────
if ($parseErrors !== []) {
    foreach ($parseErrors as $error) {
        echo $error . "\n";
    }
    echo "\n";
}

// ── Dry run: list files and exit ────────────────────────────────────────────
if ($dryRun) {
    echo str_pad('Directory', 30) . str_pad('Year', 8) . "Phase\n";
    echo str_repeat('-', 70) . "\n";

    foreach ($entries as $entry) {
        echo str_pad($entry['dir'], 30)
            . str_pad((string) $entry['year'], 8)
            . $entry['phase'] . "\n";
    }

    echo sprintf("\nTotal: %d files ready for processing.\n", count($entries));
    exit(0);
}

// ── Process files ───────────────────────────────────────────────────────────
$processor = new Boxscore\BoxscoreProcessor($mysqli_db);

$totalInserted = 0;
$totalUpdated = 0;
$totalSkipped = 0;
$totalErrors = 0;
$filesProcessed = 0;

foreach ($entries as $i => $entry) {
    $num = $i + 1;
    echo sprintf(
        "[%d/%d] %s (%d %s)\n",
        $num,
        count($entries),
        $entry['dir'],
        $entry['year'],
        $entry['phase']
    );

    // Process regular games
    try {
        $result = $processor->processScoFile($entry['path'], $entry['year'], $entry['phase'], skipSimDates: true);

        $inserted = (int) $result['gamesInserted'];
        $updated = (int) $result['gamesUpdated'];
        $skipped = (int) $result['gamesSkipped'];

        echo sprintf(
            "        Games: %d inserted, %d updated, %d skipped\n",
            $inserted,
            $updated,
            $skipped
        );

        $totalInserted += $inserted;
        $totalUpdated += $updated;
        $totalSkipped += $skipped;

        if (isset($result['error']) && $result['error'] !== '') {
            echo "        Error: {$result['error']}\n";
        }
    } catch (\Throwable $e) {
        echo "        ERROR (regular games): {$e->getMessage()}\n";
        $totalErrors++;
    }

    // Process All-Star games
    try {
        $allStarResult = $processor->processAllStarGames($entry['path'], $entry['year']);

        if ($allStarResult['messages'] !== []) {
            foreach ($allStarResult['messages'] as $msg) {
                echo "        All-Star: {$msg}\n";
            }
        }
    } catch (\Throwable $e) {
        echo "        ERROR (All-Star games): {$e->getMessage()}\n";
        $totalErrors++;
    }

    $filesProcessed++;
}

// ── Final summary ───────────────────────────────────────────────────────────
echo "\n" . str_repeat('=', 50) . "\n";
echo "BULK IMPORT COMPLETE\n";
echo str_repeat('=', 50) . "\n";
echo sprintf("Files processed: %d / %d\n", $filesProcessed, count($entries));
echo sprintf("Games inserted:  %d\n", $totalInserted);
echo sprintf("Games updated:   %d\n", $totalUpdated);
echo sprintf("Games skipped:   %d\n", $totalSkipped);
if ($totalErrors > 0) {
    echo sprintf("Errors:          %d\n", $totalErrors);
}
echo str_repeat('=', 50) . "\n";
