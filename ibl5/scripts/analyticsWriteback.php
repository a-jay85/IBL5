<?php

declare(strict_types=1);

/**
 * Analytics Write-Back Script
 *
 * Reads DuckDB-exported CSVs from analytics/data/writeback/ and UPSERTs
 * into ibl_analytics_tsi_bands and ibl_analytics_player_peaks.
 *
 * Usage:
 *   php scripts/analyticsWriteback.php              # Import both tables
 *   php scripts/analyticsWriteback.php --dry-run    # Show counts without importing
 */

// ── CLI-only guard ──────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    echo 'This script must be run from the command line.';
    exit(1);
}

// ── Minimal bootstrap ────────────────────────────────────────────────────────
$_SERVER['PHP_SELF'] = 'analyticsWriteback.php';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require_once __DIR__ . '/../vendor/autoload.php';

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

$dataDir = __DIR__ . '/../analytics/data/writeback';

if (!is_dir($dataDir)) {
    echo "Error: Write-back data directory not found: {$dataDir}\n";
    echo "Run bin/analytics-build --writeback first.\n";
    exit(1);
}

// ── Import functions ────────────────────────────────────────────────────────

/**
 * @param list<array<string, string>> $rows
 */
function importTsiBands(mysqli $db, array $rows, bool $dryRun): void
{
    echo "TSI Bands: " . count($rows) . " rows to import\n";

    if ($dryRun) {
        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO ibl_analytics_tsi_bands
            (pid, season_year, tsi_sum, tsi_band, delta_r_2gp, delta_r_ftp, delta_r_ast, age_relative_to_peak)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            tsi_sum = VALUES(tsi_sum),
            tsi_band = VALUES(tsi_band),
            delta_r_2gp = VALUES(delta_r_2gp),
            delta_r_ftp = VALUES(delta_r_ftp),
            delta_r_ast = VALUES(delta_r_ast),
            age_relative_to_peak = VALUES(age_relative_to_peak)'
    );

    if ($stmt === false) {
        echo "Error preparing TSI bands statement: {$db->error}\n";
        return;
    }

    $imported = 0;
    foreach ($rows as $row) {
        $pid = (int) $row['pid'];
        $seasonYear = (int) $row['season_year'];
        $tsiSum = (int) $row['tsi_sum'];
        $tsiBand = $row['tsi_band'];
        $deltaR2gp = $row['delta_r_2gp'] !== '' ? (int) $row['delta_r_2gp'] : null;
        $deltaRftp = $row['delta_r_ftp'] !== '' ? (int) $row['delta_r_ftp'] : null;
        $deltaRast = $row['delta_r_ast'] !== '' ? (int) $row['delta_r_ast'] : null;
        $ageRelPeak = $row['age_relative_to_peak'] !== '' ? (int) $row['age_relative_to_peak'] : null;

        $stmt->bind_param(
            'iiisiiii',
            $pid,
            $seasonYear,
            $tsiSum,
            $tsiBand,
            $deltaR2gp,
            $deltaRftp,
            $deltaRast,
            $ageRelPeak
        );

        if ($stmt->execute()) {
            $imported++;
        } else {
            echo "  Warning: Failed to import pid={$pid}, year={$seasonYear}: {$stmt->error}\n";
        }
    }

    $stmt->close();
    echo "  Imported: {$imported} rows\n";
}

/**
 * @param list<array<string, string>> $rows
 */
function importPlayerPeaks(mysqli $db, array $rows, bool $dryRun): void
{
    echo "Player Peaks: " . count($rows) . " rows to import\n";

    if ($dryRun) {
        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO ibl_analytics_player_peaks
            (pid, peak_season_year, peak_ppg, career_ppg, career_seasons)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            peak_season_year = VALUES(peak_season_year),
            peak_ppg = VALUES(peak_ppg),
            career_ppg = VALUES(career_ppg),
            career_seasons = VALUES(career_seasons)'
    );

    if ($stmt === false) {
        echo "Error preparing player peaks statement: {$db->error}\n";
        return;
    }

    $imported = 0;
    foreach ($rows as $row) {
        $pid = (int) $row['pid'];
        $peakYear = $row['peak_season_year'] !== '' ? (int) $row['peak_season_year'] : null;
        $peakPpg = $row['peak_ppg'] !== '' ? (float) $row['peak_ppg'] : null;
        $careerPpg = $row['career_ppg'] !== '' ? (float) $row['career_ppg'] : null;
        $careerSeasons = (int) $row['career_seasons'];

        $stmt->bind_param(
            'iiddi',
            $pid,
            $peakYear,
            $peakPpg,
            $careerPpg,
            $careerSeasons
        );

        if ($stmt->execute()) {
            $imported++;
        } else {
            echo "  Warning: Failed to import pid={$pid}: {$stmt->error}\n";
        }
    }

    $stmt->close();
    echo "  Imported: {$imported} rows\n";
}

/**
 * @return list<array<string, string>>
 */
function readCsv(string $filePath): array
{
    if (!file_exists($filePath)) {
        echo "Warning: CSV not found: {$filePath}\n";
        return [];
    }

    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        echo "Error: Could not open {$filePath}\n";
        return [];
    }

    $headers = fgetcsv($handle);
    if ($headers === false) {
        fclose($handle);
        return [];
    }

    $rows = [];
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) === count($headers)) {
            $rows[] = array_combine($headers, $data);
        }
    }

    fclose($handle);
    return $rows;
}

// ── Main ────────────────────────────────────────────────────────────────────

echo "Analytics Write-Back" . ($dryRun ? ' (DRY RUN)' : '') . "\n";
echo str_repeat('=', 40) . "\n\n";

// Import TSI bands
$tsiBandsFile = $dataDir . '/tsi_bands.csv';
$tsiBandsRows = readCsv($tsiBandsFile);
if (count($tsiBandsRows) > 0) {
    importTsiBands($mysqli_db, $tsiBandsRows, $dryRun);
} else {
    echo "No TSI bands data found.\n";
}

echo "\n";

// Import player peaks
$playerPeaksFile = $dataDir . '/player_peaks.csv';
$playerPeaksRows = readCsv($playerPeaksFile);
if (count($playerPeaksRows) > 0) {
    importPlayerPeaks($mysqli_db, $playerPeaksRows, $dryRun);
} else {
    echo "No player peaks data found.\n";
}

echo "\nDone.\n";
