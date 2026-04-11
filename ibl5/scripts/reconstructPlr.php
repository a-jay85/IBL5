<?php

declare(strict_types=1);

/**
 * Reconstruct a missing .plr snapshot from a nearest-prior .plr and ibl_box_scores totals.
 *
 * Overwrites the season-stat block (144-207), playoff-stats (208-267), and season highs
 * (341-435) on each player record with values derived from ibl_box_scores through the
 * target end date. All other bytes (ratings, contracts, morale, unknown offsets, career
 * totals) are preserved from the base .plr.
 *
 * Modes:
 *
 *   A. Explicit arguments — debug/advanced:
 *        php scripts/reconstructPlr.php \
 *            --base=/path/to/base.plr \
 *            --season-year=2007 \
 *            --target-end-date=2006-12-13 \
 *            --out=/path/to/output.plr
 *
 *   B. Snapshot-driven — auto-infer base + target dates:
 *        php scripts/reconstructPlr.php \
 *            --snapshot=06-07/06-07_12_reg-sim06.zip \
 *            --out=/tmp/reconstructed.plr
 *
 *   C. All known missing snapshots:
 *        php scripts/reconstructPlr.php --all --out-dir=/tmp/plr-recon/
 *
 * Requires the local Docker MariaDB to be running (see CLAUDE.md).
 */

if (PHP_SAPI !== 'cli') {
    echo 'This script must be run from the command line.';
    exit(1);
}

// ── Minimal bootstrap ────────────────────────────────────────────────────────
$_SERVER['PHP_SELF'] = 'reconstructPlr.php';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require_once __DIR__ . '/../vendor/autoload.php';

// Prepend the worktree's classes/ loader so edits in this worktree shadow the
// main repo's symlinked vendor autoloader (see memory: worktree autoloader quirks).
$localClassesDir = realpath(__DIR__ . '/../classes');
if ($localClassesDir !== false) {
    spl_autoload_register(static function (string $class) use ($localClassesDir): void {
        $path = $localClassesDir . '/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }, true, true);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/db.php';

/** @var \mysqli $mysqli_db */

// ── Known missing snapshots (surveyed across fullLeagueBackups/backups/) ────
// Each entry: [season_dir, zip_basename]. The reconstructor auto-finds the
// nearest prior .plr within the same season directory.
const KNOWN_MISSING_SNAPSHOTS = [
    '91-92/91-92_23_reg-sim18.zip',
    '01-02/01-02_27_reg-sim20.zip',
    '02-03/02-03_43_playoffs-rd1-gm4-7.zip',
    '04-05/04-05_06_reg-sim01.zip',
    '06-07/06-07_12_reg-sim06.zip',
];

// ── Parse CLI options ───────────────────────────────────────────────────────
$basePath = null;
$seasonYear = null;
$targetEndDate = null;
$outPath = null;
$snapshotRel = null;
$all = false;
$outDir = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $basePath = substr($arg, strlen('--base='));
    } elseif (str_starts_with($arg, '--season-year=')) {
        $seasonYear = (int) substr($arg, strlen('--season-year='));
    } elseif (str_starts_with($arg, '--target-end-date=')) {
        $targetEndDate = substr($arg, strlen('--target-end-date='));
    } elseif (str_starts_with($arg, '--out=')) {
        $outPath = substr($arg, strlen('--out='));
    } elseif (str_starts_with($arg, '--snapshot=')) {
        $snapshotRel = substr($arg, strlen('--snapshot='));
    } elseif (str_starts_with($arg, '--out-dir=')) {
        $outDir = rtrim(substr($arg, strlen('--out-dir=')), '/');
    } elseif ($arg === '--all') {
        $all = true;
    }
}

$backupsDir = findBackupsDir();
if ($backupsDir === null) {
    fwrite(STDERR, "No fullLeagueBackups/backups directory found. Checked: "
        . dirname(__DIR__) . "/fullLeagueBackups/backups and /Users/ajaynicolas/Documents/GitHub/IBL5/ibl5/fullLeagueBackups/backups\n");
    exit(2);
}

$repository = new PlrParser\PlrBoxScoreRepository($mysqli_db);
$inferrer = new PlrParser\PlrSimDateInferrer($repository);
$service = new PlrParser\PlrReconstructionService($repository);

// ── Mode dispatch ───────────────────────────────────────────────────────────
if ($all) {
    if ($outDir === null) {
        fwrite(STDERR, "--all requires --out-dir=PATH\n");
        exit(2);
    }
    if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
        fwrite(STDERR, "Failed to create output directory: {$outDir}\n");
        exit(2);
    }

    $exitCode = 0;
    foreach (KNOWN_MISSING_SNAPSHOTS as $rel) {
        echo "\n=== {$rel} ===\n";
        $outFile = $outDir . '/' . basename($rel, '.zip') . '.plr';
        $ok = runSnapshotMode($rel, $outFile, $backupsDir, $inferrer, $service);
        $exitCode = $exitCode === 0 && $ok ? 0 : 1;
    }
    exit($exitCode);
}

if ($snapshotRel !== null) {
    if ($outPath === null) {
        fwrite(STDERR, "--snapshot requires --out=PATH\n");
        exit(2);
    }
    $ok = runSnapshotMode($snapshotRel, $outPath, $backupsDir, $inferrer, $service);
    exit($ok ? 0 : 1);
}

// Explicit mode
if ($basePath === null || $seasonYear === null || $targetEndDate === null || $outPath === null) {
    fwrite(STDERR, "Usage: php scripts/reconstructPlr.php "
        . "[--base=PATH --season-year=YYYY --target-end-date=YYYY-MM-DD --out=PATH]"
        . "\n   or: php scripts/reconstructPlr.php --snapshot=SEASON/FILE.zip --out=PATH"
        . "\n   or: php scripts/reconstructPlr.php --all --out-dir=PATH\n");
    exit(2);
}

$result = $service->reconstruct($basePath, $seasonYear, $targetEndDate, $outPath);
printResult($result);
exit($result->hasErrors() ? 1 : 0);

// ── Helpers ─────────────────────────────────────────────────────────────────

function findBackupsDir(): ?string
{
    $candidates = [
        // Local dev: worktrees and the main checkout both land here
        dirname(__DIR__) . '/fullLeagueBackups/backups',
        // Production: archives live alongside the web root
        dirname(__DIR__) . '/backups',
    ];
    foreach ($candidates as $dir) {
        if (is_dir($dir)) {
            return $dir;
        }
    }
    return null;
}

/**
 * Run reconstruction in snapshot mode: auto-infer base + target dates.
 */
function runSnapshotMode(
    string $relPath,
    string $outFile,
    string $backupsDir,
    PlrParser\PlrSimDateInferrer $inferrer,
    PlrParser\PlrReconstructionService $service,
): bool {
    $parts = explode('/', $relPath);
    if (count($parts) !== 2) {
        fwrite(STDERR, "Invalid --snapshot path (expected SEASON/FILE.zip): {$relPath}\n");
        return false;
    }
    [$seasonDir, $zipName] = $parts;

    $seasonYear = deriveSeasonYearFromSeasonDir($seasonDir);
    if ($seasonYear === null) {
        fwrite(STDERR, "Could not derive season year from: {$seasonDir}\n");
        return false;
    }

    // Find nearest prior .plr in the same season directory
    $priorZipPath = findNearestPriorPlrZip($backupsDir . '/' . $seasonDir, $zipName);
    if ($priorZipPath === null) {
        fwrite(STDERR, "No prior zip with .plr found in {$seasonDir} before {$zipName}\n");
        return false;
    }
    echo "  base zip: " . basename($priorZipPath) . "\n";

    $basePlr = extractPlrFromZip($priorZipPath);
    if ($basePlr === null) {
        fwrite(STDERR, "Failed to extract IBL5.plr from {$priorZipPath}\n");
        return false;
    }

    $baseEnd = $inferrer->inferBaseEndDate($basePlr, $seasonYear);
    if ($baseEnd === null) {
        $latest = $inferrer->getBoxScoreCoverageForSeason($seasonYear);
        if ($latest === null) {
            fwrite(STDERR, "  ! No ibl_box_scores rows for season_year={$seasonYear}. "
                . "Reconstruction requires box scores for the target season to be ingested first.\n");
        } else {
            fwrite(STDERR, "  ! Base end date inference failed for {$priorZipPath}: "
                . "box scores exist through {$latest} but cumulative totals don't match base .plr. "
                . "Possible causes: base snapshot taken mid-sim, or data drift.\n");
        }
        @unlink($basePlr);
        return false;
    }
    echo "  base end date: {$baseEnd}\n";

    // Count steps from base zip to target zip by enumerating the directory
    $steps = countZipSlotsBetween($backupsDir . '/' . $seasonDir, $priorZipPath, $zipName);
    if ($steps < 1) {
        fwrite(STDERR, "Unexpected zip ordering: base={$priorZipPath} target={$zipName}\n");
        @unlink($basePlr);
        return false;
    }

    $targetEnd = $inferrer->inferNextSimEndDate($baseEnd, $seasonYear, $steps);
    if ($targetEnd === null) {
        fwrite(STDERR, "Could not infer target end date: base={$baseEnd} steps={$steps}\n");
        @unlink($basePlr);
        return false;
    }
    echo "  target end date: {$targetEnd} (steps={$steps})\n";

    $result = $service->reconstruct($basePlr, $seasonYear, $targetEnd, $outFile);
    @unlink($basePlr);
    printResult($result);
    return !$result->hasErrors();
}

function deriveSeasonYearFromSeasonDir(string $seasonDir): ?int
{
    // "88-89" → 1989, "06-07" → 2007, "99-00" → 2000
    if (!preg_match('/^(\d{2})-(\d{2})$/', $seasonDir, $m)) {
        return null;
    }
    $end = (int) $m[2];
    // Pivot: 00-29 → 2000s, 30-99 → 1900s
    return $end < 30 ? 2000 + $end : 1900 + $end;
}

function findNearestPriorPlrZip(string $seasonDirPath, string $targetZipName): ?string
{
    $zips = glob($seasonDirPath . '/*.zip');
    if ($zips === false) return null;
    sort($zips, SORT_STRING);

    $priorWithPlr = null;
    foreach ($zips as $zip) {
        if (basename($zip) === $targetZipName) {
            return $priorWithPlr;
        }
        if (zipContainsPlr($zip)) {
            $priorWithPlr = $zip;
        }
    }
    return null;
}

function zipContainsPlr(string $zipPath): bool
{
    $zip = new \ZipArchive();
    if ($zip->open($zipPath) !== true) return false;
    $has = $zip->locateName('IBL5.plr') !== false;
    $zip->close();
    return $has;
}

function extractPlrFromZip(string $zipPath): ?string
{
    $zip = new \ZipArchive();
    if ($zip->open($zipPath) !== true) return null;
    $tmp = tempnam(sys_get_temp_dir(), 'plr_base_');
    if ($tmp === false) { $zip->close(); return null; }
    $content = $zip->getFromName('IBL5.plr');
    $zip->close();
    if ($content === false) { @unlink($tmp); return null; }
    file_put_contents($tmp, $content);
    return $tmp;
}

/**
 * Count zip-archive slots between base and target within the same season directory.
 *
 * Returns the number of sorted-filename positions to advance from the base zip to the
 * target zip, counting **all** zips between them — including zips that lack `IBL5.plr`.
 * This equals the number of ibl_sim_dates rows to step forward IFF each archive
 * corresponds to exactly one sim, which holds for mid-season archives in practice
 * (`reg-sim06.zip`, `playoffs-rd1-gm4-7.zip`, etc.).
 *
 * Known caveats where the 1-to-1 equivalence breaks:
 *   - Preseason zips (training camp, heat rounds) don't correspond to box-score sims.
 *     Targeting an early-season snapshot makes the step count too large.
 *   - `findNearestPriorPlrZip()` may skip over intermediate zips that lack `IBL5.plr`
 *     (e.g. another missing-.plr snapshot in the same season). Those skipped slots are
 *     still counted here, because they were still real sims.
 *
 * Callers targeting early-season or stacked-missing snapshots should sanity-check the
 * inferred target date against `ibl_sim_dates` before trusting the reconstruction.
 */
function countZipSlotsBetween(string $seasonDirPath, string $baseZipPath, string $targetZipName): int
{
    $zips = glob($seasonDirPath . '/*.zip');
    if ($zips === false) return -1;
    sort($zips, SORT_STRING);

    $baseIdx = array_search($baseZipPath, $zips, true);
    $targetIdx = false;
    foreach ($zips as $i => $z) {
        if (basename($z) === $targetZipName) {
            $targetIdx = $i;
            break;
        }
    }
    if ($baseIdx === false || $targetIdx === false || $targetIdx <= $baseIdx) {
        return -1;
    }
    return $targetIdx - $baseIdx;
}

function printResult(PlrParser\PlrReconstructionResult $result): void
{
    foreach ($result->messages as $msg) echo "  " . $msg . "\n";
    echo "  Players updated:   " . $result->playersUpdated . "\n";
    echo "  Players unchanged: " . $result->playersUnchanged . "\n";
    if ($result->hasErrors()) {
        echo "  Errors:\n";
        foreach ($result->errors as $e) echo "    ! " . $e . "\n";
    }
}
