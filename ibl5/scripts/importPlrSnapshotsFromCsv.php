<?php

declare(strict_types=1);

/**
 * Import PLR Snapshots from CSV
 *
 * Loads ibl_plr_snapshots data from the analytics TSV export into MariaDB.
 * Run once on dev/prod before deploying the ibl_hist VIEW migration.
 *
 * Usage:
 *   php scripts/importPlrSnapshotsFromCsv.php              # Import all rows
 *   php scripts/importPlrSnapshotsFromCsv.php --dry-run    # Show counts only
 */

if (PHP_SAPI !== 'cli') {
    echo 'This script must be run from the command line.';
    exit(1);
}

$_SERVER['PHP_SELF'] = 'importPlrSnapshotsFromCsv.php';
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

$dryRun = in_array('--dry-run', $argv, true);
$csvPath = __DIR__ . '/../analytics/data/ibl_plr_snapshots.csv';

if (!file_exists($csvPath)) {
    echo "Error: CSV not found: {$csvPath}\n";
    echo "Run bin/analytics-export first to generate the CSV.\n";
    exit(1);
}

echo "PLR Snapshots Import" . ($dryRun ? ' (DRY RUN)' : '') . "\n";
echo str_repeat('=', 40) . "\n\n";

$handle = fopen($csvPath, 'r');
if ($handle === false) {
    echo "Error: Could not open {$csvPath}\n";
    exit(1);
}

// Read tab-separated header
$headerLine = fgets($handle);
if ($headerLine === false) {
    echo "Error: Empty CSV file\n";
    fclose($handle);
    exit(1);
}

$headers = explode("\t", trim($headerLine));
echo "CSV columns: " . count($headers) . "\n";

// Count rows
$totalRows = 0;
while (fgets($handle) !== false) {
    $totalRows++;
}
rewind($handle);
fgets($handle); // skip header again

echo "Total rows: {$totalRows}\n\n";

if ($dryRun) {
    fclose($handle);
    echo "Dry run complete.\n";
    exit(0);
}

// Prepare the upsert statement (all columns except id and created_at)
$sql = <<<'SQL'
INSERT INTO ibl_plr_snapshots
    (pid, name, season_year, snapshot_phase, source_archive, tid,
     age, pos, peak, htft, htin, wt,
     oo, od, r_drive_off, dd, po, pd, r_trans_off, td,
     r_fga, r_fgp, r_fta, r_ftp, r_tga, r_tgp,
     r_orb, r_drb, r_ast, r_stl, r_tvr, r_blk, r_foul,
     talent, skill, intangibles, clutch, consistency, exp, bird,
     cy, cyt, cy1, cy2, cy3, cy4, cy5, cy6,
     PGDepth, SGDepth, SFDepth, PFDepth, CDepth)
VALUES (?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
    source_archive = VALUES(source_archive),
    tid = VALUES(tid),
    age = VALUES(age),
    pos = VALUES(pos),
    peak = VALUES(peak),
    htft = VALUES(htft),
    htin = VALUES(htin),
    wt = VALUES(wt),
    oo = VALUES(oo), od = VALUES(od),
    r_drive_off = VALUES(r_drive_off), dd = VALUES(dd),
    po = VALUES(po), pd = VALUES(pd),
    r_trans_off = VALUES(r_trans_off), td = VALUES(td),
    r_fga = VALUES(r_fga), r_fgp = VALUES(r_fgp),
    r_fta = VALUES(r_fta), r_ftp = VALUES(r_ftp),
    r_tga = VALUES(r_tga), r_tgp = VALUES(r_tgp),
    r_orb = VALUES(r_orb), r_drb = VALUES(r_drb),
    r_ast = VALUES(r_ast), r_stl = VALUES(r_stl),
    r_tvr = VALUES(r_tvr), r_blk = VALUES(r_blk),
    r_foul = VALUES(r_foul),
    talent = VALUES(talent), skill = VALUES(skill),
    intangibles = VALUES(intangibles),
    clutch = VALUES(clutch), consistency = VALUES(consistency),
    exp = VALUES(exp), bird = VALUES(bird),
    cy = VALUES(cy), cyt = VALUES(cyt),
    cy1 = VALUES(cy1), cy2 = VALUES(cy2),
    cy3 = VALUES(cy3), cy4 = VALUES(cy4),
    cy5 = VALUES(cy5), cy6 = VALUES(cy6),
    PGDepth = VALUES(PGDepth), SGDepth = VALUES(SGDepth),
    SFDepth = VALUES(SFDepth), PFDepth = VALUES(PFDepth),
    CDepth = VALUES(CDepth)
SQL;

$stmt = $mysqli_db->prepare($sql);
if ($stmt === false) {
    echo "Error preparing statement: {$mysqli_db->error}\n";
    fclose($handle);
    exit(1);
}

$imported = 0;
$errors = 0;

while (($line = fgets($handle)) !== false) {
    $fields = explode("\t", trim($line));
    if (count($fields) < 53) {
        $errors++;
        continue;
    }

    // Map CSV columns by index (skip id=0, created_at=53)
    $pid = (int) $fields[1];
    $name = $fields[2];
    $seasonYear = (int) $fields[3];
    $snapshotPhase = $fields[4];
    $sourceArchive = $fields[5];
    $tid = (int) $fields[6];
    $age = (int) $fields[7];
    $pos = $fields[8];
    $peak = (int) $fields[9];
    $htft = (int) $fields[10];
    $htin = (int) $fields[11];
    $wt = (int) $fields[12];
    $oo = (int) $fields[13];
    $od = (int) $fields[14];
    $do_ = (int) $fields[15];
    $dd = (int) $fields[16];
    $po = (int) $fields[17];
    $pd = (int) $fields[18];
    $to_ = (int) $fields[19];
    $td = (int) $fields[20];
    $rFga = (int) $fields[21];
    $rFgp = (int) $fields[22];
    $rFta = (int) $fields[23];
    $rFtp = (int) $fields[24];
    $rTga = (int) $fields[25];
    $rTgp = (int) $fields[26];
    $rOrb = (int) $fields[27];
    $rDrb = (int) $fields[28];
    $rAst = (int) $fields[29];
    $rStl = (int) $fields[30];
    $rTo = (int) $fields[31];
    $rBlk = (int) $fields[32];
    $rFoul = (int) $fields[33];
    $talent = (int) $fields[34];
    $skill = (int) $fields[35];
    $intangibles = (int) $fields[36];
    $clutch = (int) $fields[37];
    $consistency = (int) $fields[38];
    $exp = (int) $fields[39];
    $bird = (int) $fields[40];
    $cy = (int) $fields[41];
    $cyt = (int) $fields[42];
    $cy1 = (int) $fields[43];
    $cy2 = (int) $fields[44];
    $cy3 = (int) $fields[45];
    $cy4 = (int) $fields[46];
    $cy5 = (int) $fields[47];
    $cy6 = (int) $fields[48];
    $pgDepth = (int) $fields[49];
    $sgDepth = (int) $fields[50];
    $sfDepth = (int) $fields[51];
    $pfDepth = (int) $fields[52];
    $cDepth = (int) ($fields[53] ?? '0');

    $stmt->bind_param(
        'isissiisiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiii',
        $pid, $name, $seasonYear, $snapshotPhase, $sourceArchive, $tid,
        $age, $pos, $peak, $htft, $htin, $wt,
        $oo, $od, $do_, $dd, $po, $pd, $to_, $td,
        $rFga, $rFgp, $rFta, $rFtp, $rTga, $rTgp,
        $rOrb, $rDrb, $rAst, $rStl, $rTo, $rBlk, $rFoul,
        $talent, $skill, $intangibles, $clutch, $consistency, $exp, $bird,
        $cy, $cyt, $cy1, $cy2, $cy3, $cy4, $cy5, $cy6,
        $pgDepth, $sgDepth, $sfDepth, $pfDepth, $cDepth
    );

    if ($stmt->execute()) {
        $imported++;
    } else {
        $errors++;
        if ($errors <= 10) {
            fprintf(STDERR, "  Warning: pid=%d year=%d phase=%s: %s\n", $pid, $seasonYear, $snapshotPhase, $stmt->error);
        }
    }

    if ($imported % 5000 === 0) {
        fprintf(STDERR, "  Progress: %d / %d rows\n", $imported, $totalRows);
    }
}

$stmt->close();
fclose($handle);

echo "Imported: {$imported} rows\n";
if ($errors > 0) {
    echo "Errors: {$errors} rows\n";
}
echo "\nDone.\n";
