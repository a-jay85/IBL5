<?php

declare(strict_types=1);

/**
 * Migration script: Normalize ibl_team_awards Award column.
 *
 * Issues fixed:
 * 1. Multiple awards crammed into a single record (separated by <BR> tags)
 *    → Split into individual rows (one award per row).
 * 2. HTML tags (<B>, </b>, <BR>, </font>, etc.) embedded in Award text
 *    → Stripped to plain text.
 * 3. Redundant year prefixes ("2004 Central Division Champions")
 *    → Stripped since the `year` column already carries the year.
 * 4. Typos ("Champioins", "Chmpions") → Normalized to "Champions".
 * 5. Inconsistent naming ("Draft Lottery Winners" vs "IBL Draft Lottery Winners")
 *    → Normalized to canonical form.
 * 6. Record ID 97 contains awards from two different years (2001 and 2000)
 *    → Split into correct years.
 *
 * Usage: php migrations/migrate_team_awards.php [--dry-run]
 */

require_once __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/../config.php';
include __DIR__ . '/../db/db.php';

/** @var mysqli $mysqli_db */

$dryRun = in_array('--dry-run', $argv, true);

if ($dryRun) {
    echo "=== DRY RUN MODE (no changes will be made) ===\n\n";
}

// ---------------------------------------------------------------------------
// 1. Fetch all existing records
// ---------------------------------------------------------------------------

$result = $mysqli_db->query("SELECT ID, year, name, Award FROM ibl_team_awards ORDER BY ID ASC");
if ($result === false) {
    die("Failed to query ibl_team_awards: " . $mysqli_db->error . "\n");
}

/** @var list<array{ID: int, year: int, name: string, Award: string}> $rows */
$rows = $result->fetch_all(MYSQLI_ASSOC);
$result->free();

echo "Found " . count($rows) . " existing ibl_team_awards rows to process.\n\n";

// ---------------------------------------------------------------------------
// 2. Parse each row — extract individual awards
// ---------------------------------------------------------------------------

/**
 * Normalize an award string: strip HTML, year prefix, fix typos.
 *
 * @return array{year: int, award: string} The year (from prefix or fallback) and cleaned award name
 */
function normalizeAward(string $raw, int $fallbackYear): array
{
    // Strip all HTML tags
    $text = strip_tags($raw);

    // Collapse whitespace (newlines, multiple spaces, etc.)
    $text = (string) preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    if ($text === '') {
        return ['year' => $fallbackYear, 'award' => ''];
    }

    // Extract year prefix if present (e.g., "2004 Central Division Champions")
    $year = $fallbackYear;
    if (preg_match('/^(\d{4})\s+(.+)$/', $text, $m) === 1) {
        $year = (int) $m[1];
        $text = trim($m[2]);
    }

    // Fix typos
    $text = str_replace('Champioins', 'Champions', $text);
    $text = str_replace('Chmpions', 'Champions', $text);

    // Normalize "Draft Lottery Winners" → "IBL Draft Lottery Winners"
    if ($text === 'Draft Lottery Winners') {
        $text = 'IBL Draft Lottery Winners';
    }

    return ['year' => $year, 'award' => $text];
}

/** @var list<array{original_id: int, year: int, name: string, award: string}> $parsed */
$parsed = [];
$warnings = [];

foreach ($rows as $row) {
    $awardHtml = $row['Award'];
    $recordYear = (int) $row['year'];
    $recordName = $row['name'];
    $recordId = (int) $row['ID'];

    // Split on any <BR>, </BR>, <br>, </br> variant (case-insensitive)
    // Use a regex that matches <BR>, <BR/>, </BR>, <br>, <br/>, </br>
    $parts = preg_split('/<\/?br\s*\/?>/i', $awardHtml);
    if ($parts === false) {
        $warnings[] = "ID={$recordId}: preg_split failed on Award field";
        continue;
    }

    $awardIndex = 0;
    foreach ($parts as $part) {
        $normalized = normalizeAward($part, $recordYear);
        if ($normalized['award'] === '') {
            continue;
        }

        $parsed[] = [
            'original_id' => $recordId,
            'year'        => $normalized['year'],
            'name'        => $recordName,
            'award'       => $normalized['award'],
            'index'       => $awardIndex,
        ];
        $awardIndex++;
    }

    if ($awardIndex === 0) {
        $warnings[] = "ID={$recordId}: No awards parsed from: {$awardHtml}";
    }
}

// ---------------------------------------------------------------------------
// 3. Report parsing results
// ---------------------------------------------------------------------------

echo str_repeat('=', 60) . "\n";
echo "Parsed " . count($parsed) . " individual award entries from " . count($rows) . " records.\n";

if ($warnings !== []) {
    echo "\n--- WARNINGS ---\n";
    foreach ($warnings as $w) {
        echo "  WARNING: {$w}\n";
    }
    echo "\n";
}

// Show what will happen
$updates = [];
$inserts = [];

foreach ($parsed as $entry) {
    if ($entry['index'] === 0) {
        // First award: update the existing record
        $updates[] = $entry;
    } else {
        // Additional awards: insert new records
        $inserts[] = $entry;
    }
}

echo "\n" . count($updates) . " existing records will be UPDATED (first award per record).\n";
echo count($inserts) . " new records will be INSERTED (additional awards split out).\n\n";

// Show the canonical award names
$uniqueAwards = [];
foreach ($parsed as $entry) {
    $uniqueAwards[$entry['award']] = true;
}
ksort($uniqueAwards);
echo "Canonical award names (" . count($uniqueAwards) . "):\n";
foreach (array_keys($uniqueAwards) as $award) {
    echo "  - {$award}\n";
}
echo "\n";

// ---------------------------------------------------------------------------
// 4. Preview changes
// ---------------------------------------------------------------------------

echo str_repeat('=', 60) . "\n";
echo "UPDATES (existing records — cleaned Award text):\n";
echo sprintf("  %-5s %-6s %-14s %s\n", "ID", "Year", "Team", "Award");
echo "  " . str_repeat('-', 70) . "\n";
foreach ($updates as $entry) {
    $yearChanged = $entry['year'] !== (int) $entry['original_id']; // not meaningful, check against original
    echo sprintf("  %-5d %-6d %-14s %s\n", $entry['original_id'], $entry['year'], $entry['name'], $entry['award']);
}

echo "\nINSERTS (new rows from multi-award records):\n";
echo sprintf("  %-5s %-6s %-14s %s\n", "From", "Year", "Team", "Award");
echo "  " . str_repeat('-', 70) . "\n";
foreach ($inserts as $entry) {
    echo sprintf("  %-5d %-6d %-14s %s\n", $entry['original_id'], $entry['year'], $entry['name'], $entry['award']);
}

// ---------------------------------------------------------------------------
// 5. Apply changes
// ---------------------------------------------------------------------------

if ($dryRun) {
    echo "\n=== DRY RUN COMPLETE — no changes made ===\n";
    exit(0);
}

echo "\n--- Applying changes ---\n";

// 5a. Update existing records (first award per original record)
$updateStmt = $mysqli_db->prepare("UPDATE ibl_team_awards SET year = ?, Award = ? WHERE ID = ?");
if ($updateStmt === false) {
    die("Failed to prepare UPDATE: " . $mysqli_db->error . "\n");
}

$updatedCount = 0;
foreach ($updates as $entry) {
    $updateStmt->bind_param('isi', $entry['year'], $entry['award'], $entry['original_id']);
    if (!$updateStmt->execute()) {
        echo "  ERROR updating ID {$entry['original_id']}: {$updateStmt->error}\n";
    } else {
        $updatedCount++;
    }
}
$updateStmt->close();
echo "Updated {$updatedCount} existing records.\n";

// 5b. Insert new records for additional awards
if ($inserts !== []) {
    // Get next available ID
    $maxResult = $mysqli_db->query("SELECT MAX(ID) AS max_id FROM ibl_team_awards");
    if ($maxResult === false) {
        die("Failed to get MAX(ID): " . $mysqli_db->error . "\n");
    }
    $maxRow = $maxResult->fetch_assoc();
    $nextId = ((int) ($maxRow['max_id'] ?? 0)) + 1;
    $maxResult->free();

    $insertStmt = $mysqli_db->prepare("INSERT INTO ibl_team_awards (ID, year, name, Award) VALUES (?, ?, ?, ?)");
    if ($insertStmt === false) {
        die("Failed to prepare INSERT: " . $mysqli_db->error . "\n");
    }

    $insertedCount = 0;
    foreach ($inserts as $entry) {
        $insertStmt->bind_param('iiss', $nextId, $entry['year'], $entry['name'], $entry['award']);
        if (!$insertStmt->execute()) {
            echo "  ERROR inserting (ID={$nextId}): {$insertStmt->error}\n";
        } else {
            echo "  Inserted ID={$nextId}: [{$entry['year']}] {$entry['name']} — {$entry['award']}\n";
            $insertedCount++;
            $nextId++;
        }
    }
    $insertStmt->close();
    echo "Inserted {$insertedCount} new records.\n";
}

// ---------------------------------------------------------------------------
// 6. Verify
// ---------------------------------------------------------------------------

echo "\n--- Verification ---\n";

$countResult = $mysqli_db->query("SELECT COUNT(*) AS cnt FROM ibl_team_awards");
if ($countResult !== false) {
    $countRow = $countResult->fetch_assoc();
    echo "Total rows in ibl_team_awards: {$countRow['cnt']}\n";
    $countResult->free();
}

// Check for any remaining HTML
$htmlCheck = $mysqli_db->query("SELECT ID, Award FROM ibl_team_awards WHERE Award LIKE '%<%' OR Award LIKE '%>%'");
if ($htmlCheck !== false) {
    $htmlRows = $htmlCheck->fetch_all(MYSQLI_ASSOC);
    $htmlCheck->free();
    if ($htmlRows !== []) {
        echo "\nWARNING: " . count($htmlRows) . " records still contain HTML:\n";
        foreach ($htmlRows as $hr) {
            echo "  ID={$hr['ID']}: {$hr['Award']}\n";
        }
    } else {
        echo "No remaining HTML tags found.\n";
    }
}

// Check for any duplicate (year, name, Award) combos
$dupeCheck = $mysqli_db->query(
    "SELECT year, name, Award, COUNT(*) AS cnt FROM ibl_team_awards GROUP BY year, name, Award HAVING cnt > 1"
);
if ($dupeCheck !== false) {
    $dupes = $dupeCheck->fetch_all(MYSQLI_ASSOC);
    $dupeCheck->free();
    if ($dupes !== []) {
        echo "\nWARNING: " . count($dupes) . " duplicate (year, name, Award) combinations found:\n";
        foreach ($dupes as $d) {
            echo "  [{$d['year']}] {$d['name']} — {$d['Award']} (x{$d['cnt']})\n";
        }
    } else {
        echo "No duplicate entries found.\n";
    }
}

echo "\nDone.\n";
