<?php

declare(strict_types=1);

/**
 * Patch the blank 2006-07 All-Star Weekend records into 06-07_36_finals.zip's IBL5.sco.
 *
 * The two ASG records (bytes 0..3999) in that archive are entirely blank (all 0x20),
 * which causes the bulk importer to silently skip them. This script encodes the
 * known-good reconstructed stats (transcribed from the HTML box scores and stored in
 * reconstruct_2007_asg_boxscores.php) into those bytes so a future archive-only
 * reimport reproduces the 48 DB rows with zero special-casing.
 *
 * This script is COMPLEMENTARY to reconstruct_2007_asg_boxscores.php:
 *   - reconstruct_2007_asg_boxscores.php  — writes rows into the LIVE DB now
 *   - this script                         — makes the ARCHIVE self-sufficient
 *
 * The stat source of truth is the $games array in reconstruct_2007_asg_boxscores.php.
 * Do not re-transcribe stats here — copy them verbatim from that file.
 *
 * Integrity guards (abort on violation before any write):
 *   1. IBL5.sco must be exactly 12,781,648 bytes.
 *   2. Bytes 0..3999 of IBL5.sco must be entirely spaces (blank precondition).
 *   3. All 30 zip members' decompressed SHA-256 hashes are recorded before write.
 *   4. After write: IBL5.sco tail (bytes 1,000,000..EOF) hash identical to pre-write.
 *   5. After write: every member except IBL5.sco hash-identical to pre-write.
 *
 * @see /docs/decisions/0051-reconstructed-2007-asg-boxscores-in-finals-sco.md
 * @see ibl5/scripts/reconstruct_2007_asg_boxscores.php (stat source of truth)
 *
 * Usage:
 *   php patch_2007_asg_sco.php           # dry-run (no writes)
 *   php patch_2007_asg_sco.php --apply   # patch the archive in place
 */

use JsbParser\ScoFileParser;
use JsbParser\ScoFileWriter;

if (PHP_SAPI !== 'cli') {
    echo 'This script must be run from the command line.';
    exit(1);
}

// ── Minimal bootstrap (mirrors reconstruct_2007_asg_boxscores.php) ───────────
$_SERVER['PHP_SELF'] = 'patch_2007_asg_sco.php';
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

$apply = in_array('--apply', $argv, true);

// ── Constants ────────────────────────────────────────────────────────────────
const PATCH_ATTENDANCE = 5244;
const PATCH_CAPACITY   = 20000;

// ── Stat dataset (verbatim from reconstruct_2007_asg_boxscores.php) ───────────
// Order: [pid, pos, min, fgm, fga, ftm, fta, tpm, tpa, orb, reb, ast, stl, tov, blk, pf]
$games = [
    'Rising Stars Game' => [
        'date'         => '2007-02-02',
        'visitor_teamid' => 40,
        'home_teamid'    => 41,
        'visitor_name'   => 'Rookies',
        'home_name'      => 'Sophomores',
        'visitor_q'      => [34, 39, 30, 31, 0],
        'home_q'         => [34, 31, 39, 41, 0],
        'visitor_team'   => [55, 119, 16, 21, 8, 21, 25, 66, 30, 11, 22, 8, 19],
        'home_team'      => [64, 127, 5, 9, 12, 27, 24, 67, 34, 10, 21, 14, 21],
        'visitor_players' => [
            [5936, 'PG', 32, 7, 18, 5, 5, 0, 3, 3, 4, 7, 0, 3, 0, 3],
            [5938, 'PG', 16, 2, 7, 0, 0, 1, 5, 1, 2, 1, 0, 2, 1, 0],
            [5931, 'SG', 32, 10, 21, 3, 5, 2, 5, 4, 8, 0, 3, 1, 1, 2],
            [5930, 'SG', 19, 3, 5, 2, 3, 2, 2, 1, 2, 2, 2, 1, 1, 1],
            [5937, 'SF', 29, 6, 13, 0, 0, 1, 2, 0, 4, 11, 1, 5, 0, 2],
            [5939, 'SF', 13, 6, 11, 1, 2, 0, 0, 1, 3, 4, 0, 3, 1, 4],
            [5929, 'PF', 30, 9, 18, 2, 2, 2, 3, 3, 7, 2, 1, 2, 1, 1],
            [5935, 'PF', 30, 7, 14, 3, 4, 0, 1, 6, 12, 2, 4, 3, 1, 3],
            [5942, 'C', 14, 2, 5, 0, 0, 0, 0, 3, 7, 0, 0, 2, 1, 1],
            [5964, 'C', 22, 3, 7, 0, 0, 0, 0, 1, 9, 1, 0, 0, 1, 2],
        ],
        'home_players'    => [
            [5640, 'PG', 31, 13, 23, 0, 0, 0, 2, 4, 11, 12, 3, 6, 3, 1],
            [5649, 'PG', 13, 2, 5, 3, 4, 1, 2, 0, 1, 5, 3, 2, 0, 0],
            [5642, 'SG', 20, 11, 14, 1, 3, 1, 2, 1, 2, 6, 0, 3, 0, 2],
            [5659, 'SG', 30, 10, 24, 0, 0, 6, 11, 0, 5, 5, 2, 3, 2, 2],
            [5645, 'SF', 31, 9, 19, 1, 1, 2, 5, 0, 5, 1, 0, 0, 0, 4],
            [5685, 'SF', 20, 3, 9, 0, 0, 2, 4, 0, 1, 2, 1, 0, 0, 2],
            [5646, 'PF', 17, 3, 5, 0, 0, 0, 0, 2, 6, 0, 1, 2, 0, 5],
            [5663, 'PF', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
            [5641, 'C', 31, 7, 17, 0, 0, 0, 1, 10, 16, 2, 0, 1, 4, 4],
            [5644, 'C', 43, 6, 11, 0, 1, 0, 0, 6, 17, 1, 0, 4, 5, 1],
        ],
    ],
    'All-Star Game' => [
        'date'         => '2007-02-03',
        'visitor_teamid' => 50,
        'home_teamid'    => 51,
        'visitor_name'   => 'Team Diep',
        'home_name'      => 'Team Lilley',
        'visitor_q'      => [43, 31, 44, 26, 0],
        'home_q'         => [43, 48, 55, 36, 0],
        'visitor_team'   => [53, 108, 33, 38, 5, 20, 11, 53, 23, 4, 20, 10, 23],
        'home_team'      => [72, 132, 26, 29, 12, 30, 20, 67, 39, 16, 10, 9, 28],
        'visitor_players' => [
            [3852, 'PG', 13, 3, 8, 2, 2, 1, 4, 0, 2, 3, 0, 1, 0, 3],
            [5640, 'PG', 17, 3, 4, 4, 4, 0, 0, 1, 3, 5, 0, 2, 0, 0],
            [3851, 'PG', 28, 9, 14, 4, 4, 1, 3, 0, 3, 4, 2, 2, 0, 1],
            [4148, 'PF', 21, 2, 4, 5, 5, 0, 0, 2, 7, 2, 0, 1, 0, 4],
            [5258, 'SF', 25, 6, 13, 5, 6, 0, 1, 0, 5, 4, 1, 3, 0, 0],
            [4500, 'C', 22, 9, 12, 7, 7, 2, 2, 3, 7, 0, 0, 0, 5, 2],
            [3282, 'SG', 15, 0, 2, 2, 2, 0, 1, 0, 3, 3, 0, 2, 0, 4],
            [3561, 'SG', 13, 3, 7, 0, 0, 1, 2, 0, 1, 0, 0, 3, 0, 0],
            [2975, 'C', 25, 7, 15, 3, 6, 0, 0, 1, 9, 1, 1, 3, 4, 4],
            [3277, 'SF', 15, 4, 13, 0, 0, 0, 5, 0, 0, 0, 0, 0, 0, 2],
            [5265, 'SG', 20, 5, 6, 0, 0, 0, 0, 0, 3, 0, 0, 1, 0, 0],
            [4507, 'PF', 21, 2, 10, 1, 2, 0, 2, 1, 5, 1, 0, 2, 1, 3],
        ],
        'home_players'    => [
            [4150, 'PG', 18, 6, 10, 0, 0, 1, 2, 1, 4, 3, 2, 2, 1, 1],
            [3556, 'PG', 18, 7, 13, 4, 4, 0, 3, 1, 7, 5, 2, 1, 1, 1],
            [3552, 'SG', 18, 6, 13, 0, 0, 2, 5, 0, 3, 4, 2, 0, 1, 3],
            [5261, 'PF', 22, 4, 7, 7, 8, 1, 1, 1, 2, 2, 0, 0, 1, 1],
            [3555, 'C', 12, 3, 6, 0, 0, 0, 0, 2, 7, 0, 0, 1, 0, 0],
            [5259, 'SG', 27, 9, 23, 1, 2, 1, 4, 7, 8, 8, 1, 1, 1, 2],
            [4490, 'C', 20, 8, 14, 3, 3, 3, 5, 1, 5, 1, 1, 1, 0, 3],
            [4492, 'C', 20, 6, 11, 2, 2, 0, 0, 2, 9, 1, 0, 1, 1, 3],
            [4494, 'SG', 16, 7, 9, 6, 6, 1, 2, 1, 3, 2, 3, 1, 0, 3],
            [4502, 'PG', 11, 2, 3, 0, 0, 0, 1, 0, 3, 3, 0, 0, 0, 6],
            [4824, 'C', 29, 8, 10, 3, 4, 0, 1, 2, 8, 0, 3, 2, 1, 5],
            [4825, 'PG', 23, 6, 13, 0, 0, 3, 6, 1, 5, 10, 2, 0, 2, 0],
        ],
    ],
];

// ── Name resolution (same logic as reconstruct_2007_asg_boxscores.php) ────────
$nameFor = static function (int $pid) use ($mysqli_db): string {
    if ($pid === 5265) {
        return 'Drazen Dalipagic';
    }
    $stmt = $mysqli_db->prepare('SELECT name FROM ibl_plr WHERE pid = ? LIMIT 1');
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row === null) {
        throw new RuntimeException("pid {$pid} not found in ibl_plr");
    }
    return mb_substr((string) $row['name'], 0, 16);
};

// ── Build encoder-ready game arrays (apply 2gm/2ga/drb derivations) ──────────
$buildGameArray = static function (array $g) use ($nameFor): array {
    $derivePlayers = static function (array $players) use ($nameFor): array {
        $result = [];
        foreach ($players as $p) {
            [$pid, $pos, $min, $fgm, $fga, $ftm, $fta, $tpm, $tpa, $orb, $reb, $ast, $stl, $tov, $blk, $pf] = $p;
            $result[] = [
                'name'    => $nameFor($pid),
                'pos'     => $pos,
                'pid'     => $pid,
                'min'     => $min,
                'twoGM'   => $fgm - $tpm,
                'twoGA'   => $fga - $tpa,
                'ftm'     => $ftm,
                'fta'     => $fta,
                'threeGM' => $tpm,
                'threeGA' => $tpa,
                'orb'     => $orb,
                'drb'     => $reb - $orb,
                'ast'     => $ast,
                'stl'     => $stl,
                'tov'     => $tov,
                'blk'     => $blk,
                'pf'      => $pf,
            ];
        }
        return $result;
    };

    [$fgm, $fga, $ftm, $fta, $tpm, $tpa, $orb, $reb, $ast, $stl, $tov, $blk, $pf] = $g['visitor_team'];
    $vTeam = [
        'twoGM' => $fgm - $tpm, 'twoGA' => $fga - $tpa,
        'ftm' => $ftm, 'fta' => $fta,
        'threeGM' => $tpm, 'threeGA' => $tpa,
        'orb' => $orb, 'drb' => $reb - $orb,
        'ast' => $ast, 'stl' => $stl, 'tov' => $tov, 'blk' => $blk, 'pf' => $pf,
    ];

    [$fgm, $fga, $ftm, $fta, $tpm, $tpa, $orb, $reb, $ast, $stl, $tov, $blk, $pf] = $g['home_team'];
    $hTeam = [
        'twoGM' => $fgm - $tpm, 'twoGA' => $fga - $tpa,
        'ftm' => $ftm, 'fta' => $fta,
        'threeGM' => $tpm, 'threeGA' => $tpa,
        'orb' => $orb, 'drb' => $reb - $orb,
        'ast' => $ast, 'stl' => $stl, 'tov' => $tov, 'blk' => $blk, 'pf' => $pf,
    ];

    return [
        'visitor_name'    => $g['visitor_name'],
        'home_name'       => $g['home_name'],
        'visitor_q'       => $g['visitor_q'],
        'home_q'          => $g['home_q'],
        'visitor_teamid'  => $g['visitor_teamid'],
        'home_teamid'     => $g['home_teamid'],
        'attendance'      => PATCH_ATTENDANCE,
        'capacity'        => PATCH_CAPACITY,
        'visitor_team'    => $vTeam,
        'home_team'       => $hTeam,
        'visitor_players' => $derivePlayers($g['visitor_players']),
        'home_players'    => $derivePlayers($g['home_players']),
    ];
};

echo $apply ? "APPLYING patch...\n\n" : "DRY RUN (pass --apply to write)\n\n";

try {
    // ── Step 1: Open archive ──────────────────────────────────────────────────
    $archivePath = realpath(__DIR__ . '/../backups/06-07/06-07_36_finals.zip');
    if ($archivePath === false) {
        throw new RuntimeException('Archive not found at ' . __DIR__ . '/../backups/06-07/06-07_36_finals.zip');
    }

    $zip = new ZipArchive();
    if ($zip->open($archivePath) !== true) {
        throw new RuntimeException("Cannot open {$archivePath}");
    }

    $sco = $zip->getFromName('IBL5.sco');
    if ($sco === false) {
        throw new RuntimeException('IBL5.sco not found in archive');
    }

    // ── Step 2: File-length guard ─────────────────────────────────────────────
    if (strlen($sco) !== ScoFileWriter::SCO_FILE_SIZE) {
        throw new RuntimeException(sprintf(
            'IBL5.sco is %d bytes, expected %d',
            strlen($sco),
            ScoFileWriter::SCO_FILE_SIZE,
        ));
    }
    echo sprintf("IBL5.sco: %d bytes ✓\n", strlen($sco));

    // ── Step 3: Blank-target guard ────────────────────────────────────────────
    if (trim(substr($sco, 0, 4000)) !== '') {
        throw new RuntimeException('IBL5.sco bytes 0..3999 are NOT entirely spaces — archive may already be patched or corrupt');
    }
    echo "Bytes 0..3999: entirely spaces (blank precondition) ✓\n";

    // ── Step 4: Offset guard — read real record at 1,000,000 ─────────────────
    $realRecord = substr($sco, ScoFileParser::HEADER_OFFSET_BYTES, ScoFileParser::RECORD_SIZE);
    // Find first non-blank record for sanity check
    $numRecords = intdiv(strlen($sco) - ScoFileParser::HEADER_OFFSET_BYTES, ScoFileParser::RECORD_SIZE);
    $firstNonBlankIdx = null;
    for ($i = 0; $i < min($numRecords, 2000); $i++) {
        $rec = substr($sco, ScoFileParser::HEADER_OFFSET_BYTES + $i * ScoFileParser::RECORD_SIZE, ScoFileParser::RECORD_SIZE);
        if (trim($rec) !== '') {
            $firstNonBlankIdx = $i;
            $realRecord = $rec;
            break;
        }
    }
    if ($firstNonBlankIdx === null) {
        throw new RuntimeException('No non-blank records found after offset 1,000,000 — cannot verify layout');
    }
    $slot0 = ScoFileParser::extractPlayerSlot($realRecord, 0);
    $slotName = trim(substr($slot0, 0, 16));
    if ($slotName === '') {
        throw new RuntimeException("First slot of real record #{$firstNonBlankIdx} has empty name — unexpected layout");
    }
    echo "Offset guard: real record #{$firstNonBlankIdx} found, slot-0 name=[{$slotName}] ✓\n";

    // ── Step 5: Baseline hash all 30 members ─────────────────────────────────
    $memberCount = $zip->numFiles;
    echo "Archive has {$memberCount} members\n";

    $baselineHashes = [];
    for ($i = 0; $i < $memberCount; $i++) {
        $stat = $zip->statIndex($i);
        if ($stat === false) {
            throw new RuntimeException("Cannot stat member {$i}");
        }
        $content = $zip->getFromIndex($i);
        if ($content === false) {
            throw new RuntimeException("Cannot read member {$stat['name']}");
        }
        $baselineHashes[$stat['name']] = hash('sha256', $content);
    }
    echo sprintf("Baseline: %d member hashes recorded ✓\n", count($baselineHashes));

    $zip->close();

    // ── Step 6: Resolve names from ibl_plr ────────────────────────────────────
    echo "\nResolving player names from ibl_plr...\n";

    $risingStarsArray = $buildGameArray($games['Rising Stars Game']);
    $allStarArray     = $buildGameArray($games['All-Star Game']);

    echo "Names resolved ✓\n";

    // ── Step 7: Build 4000-byte block ─────────────────────────────────────────
    $block = ScoFileWriter::buildAllStarHeaderBlock($risingStarsArray, $allStarArray);
    echo sprintf("Block: %d bytes ✓\n", strlen($block));

    // ── Step 8: Splice with integrity guards ──────────────────────────────────
    $patched = ScoFileWriter::spliceAllStarBlock($sco, $block);
    echo sprintf("Splice: length %d, tail hash preserved ✓\n", strlen($patched));

    // ── Step 9: Report proposed change ───────────────────────────────────────
    $oldHash = hash('sha256', substr($sco, 0, 4000));
    $newHash = hash('sha256', substr($patched, 0, 4000));
    echo "\nProposed change in IBL5.sco:\n";
    echo "  bytes:    0..3999 (4000 bytes)\n";
    echo "  before:   {$oldHash}\n";
    echo "  after:    {$newHash}\n";

    if (!$apply) {
        echo "\nDry run complete — no files written.\n";
        exit(0);
    }

    // ── Step 10: Write patched copy, verify, atomic-rename ───────────────────
    $tempPath = $archivePath . '.patch_tmp_' . getmypid();

    if (!copy($archivePath, $tempPath)) {
        throw new RuntimeException("Cannot create temp copy at {$tempPath}");
    }

    $zipOut = new ZipArchive();
    if ($zipOut->open($tempPath) !== true) {
        throw new RuntimeException("Cannot open temp archive {$tempPath}");
    }

    if (!$zipOut->addFromString('IBL5.sco', $patched)) {
        $zipOut->close();
        unlink($tempPath);
        throw new RuntimeException('addFromString failed for IBL5.sco');
    }

    $zipOut->close();

    // Verify: re-open patched copy and check all members
    $zipVerify = new ZipArchive();
    if ($zipVerify->open($tempPath) !== true) {
        unlink($tempPath);
        throw new RuntimeException("Cannot re-open patched archive for verification");
    }

    $patchedSco = $zipVerify->getFromName('IBL5.sco');
    if ($patchedSco === false) {
        $zipVerify->close();
        unlink($tempPath);
        throw new RuntimeException('IBL5.sco missing from patched archive');
    }

    // Verify sco tail hash identical
    $patchedTailHash = hash('sha256', substr($patchedSco, ScoFileParser::HEADER_OFFSET_BYTES));
    $originalTailHash = hash('sha256', substr($sco, ScoFileParser::HEADER_OFFSET_BYTES));
    if ($patchedTailHash !== $originalTailHash) {
        $zipVerify->close();
        unlink($tempPath);
        throw new RuntimeException('Patched IBL5.sco tail hash mismatch');
    }

    // Verify all other members unchanged
    $unchanged = 0;
    for ($i = 0; $i < $zipVerify->numFiles; $i++) {
        $stat = $zipVerify->statIndex($i);
        if ($stat === false) {
            continue;
        }
        if ($stat['name'] === 'IBL5.sco') {
            continue;
        }
        $content = $zipVerify->getFromIndex($i);
        if ($content === false) {
            continue;
        }
        $actualHash = hash('sha256', $content);
        $expectedHash = $baselineHashes[$stat['name']] ?? null;
        if ($expectedHash === null || $actualHash !== $expectedHash) {
            $zipVerify->close();
            unlink($tempPath);
            throw new RuntimeException("Member {$stat['name']} hash changed unexpectedly after patch");
        }
        $unchanged++;
    }
    $zipVerify->close();

    echo "\nVerification:\n";
    echo "  IBL5.sco tail hash: identical ✓\n";
    echo "  Other members unchanged: {$unchanged} ✓\n";

    // Atomic rename
    if (!rename($tempPath, $archivePath)) {
        throw new RuntimeException("Cannot rename {$tempPath} → {$archivePath}");
    }

    echo "\nPatched archive written to: {$archivePath}\n";

    // Emit sidecar note (gitignored; informational only)
    $sidecar = dirname($archivePath) . '/06-07_36_finals.README.txt';
    file_put_contents($sidecar, implode("\n", [
        '06-07_36_finals.zip — patch notes',
        '',
        'IBL5.sco bytes 0..3999 were blank (all 0x20) in the original sim archive.',
        'They have been patched with reconstructed 2006-07 All-Star Weekend stats.',
        '',
        'Source: ibl5/scripts/patch_2007_asg_sco.php',
        'Stats:  ibl5/scripts/reconstruct_2007_asg_boxscores.php',
        'ADR:    ibl5/docs/decisions/0051-reconstructed-2007-asg-boxscores-in-finals-sco.md',
        '',
        sprintf('Patched: %s', date('Y-m-d H:i:s')),
        '',
        'Do not commit this file or the zip to git.',
    ]) . "\n");

    echo "Sidecar note: {$sidecar}\n";
    echo "\nDone.\n";

} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
