<?php

declare(strict_types=1);

/**
 * Migration script: Convert ibl_gm_history Award column into ibl_gm_awards table.
 *
 * Parses the HTML-formatted Award field from ibl_gm_history, extracts individual
 * GM awards (GM of the Year, ASG Head Coach, ASG Asst Coach, etc.), and inserts
 * them into ibl_gm_awards with the same schema as ibl_awards.
 *
 * Usage: php migrations/migrate_gm_awards.php [--dry-run]
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
// 1. Create ibl_gm_awards table (same schema as ibl_awards)
// ---------------------------------------------------------------------------

$createTableSQL = <<<'SQL'
CREATE TABLE IF NOT EXISTS `ibl_gm_awards` (
  `year` int NOT NULL DEFAULT 0,
  `Award` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `table_ID` int NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`table_ID`),
  KEY `idx_year` (`year`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

if (!$dryRun) {
    if (!$mysqli_db->query($createTableSQL)) {
        die("Failed to create ibl_gm_awards table: " . $mysqli_db->error . "\n");
    }
    echo "Created ibl_gm_awards table (or already exists).\n\n";
} else {
    echo "Would create ibl_gm_awards table.\n\n";
}

// ---------------------------------------------------------------------------
// 2. Fetch all ibl_gm_history rows
// ---------------------------------------------------------------------------

$result = $mysqli_db->query("SELECT prim, year, name, Award FROM ibl_gm_history ORDER BY prim ASC");
if ($result === false) {
    die("Failed to query ibl_gm_history: " . $mysqli_db->error . "\n");
}

$rows = $result->fetch_all(MYSQLI_ASSOC);
$result->free();

echo "Found " . count($rows) . " ibl_gm_history rows to process.\n\n";

// ---------------------------------------------------------------------------
// 3. Parse each row and extract individual awards
// ---------------------------------------------------------------------------

/** @var list<array{year: int, award: string, name: string}> $awards */
$awards = [];
$gaps = [];

foreach ($rows as $row) {
    $awardHtml = $row['Award'];

    // Extract GM name from bold tag at the start: <B>Name</B> or <b>Name</b>
    if (!preg_match('/<[Bb]>(.*?)<\/[Bb]>/i', $awardHtml, $nameMatch)) {
        $gaps[] = "prim={$row['prim']}: Could not extract GM name from Award field";
        continue;
    }
    $gmName = trim($nameMatch[1]);

    // Remove the bold name from the Award text to get the remaining awards
    $awardText = substr($awardHtml, strlen($nameMatch[0]));

    // Split by <BR> tags (case-insensitive)
    $lines = preg_split('/<br\s*\/?>/i', $awardText);
    if ($lines === false) {
        continue;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        // Strip any remaining HTML tags
        $line = strip_tags($line);
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        // Pattern 1: "Award Name: year1, year2, ..." (e.g., "GM of the Year: 1990, 1993")
        if (preg_match('/^(.+?):\s*(.+)$/', $line, $awardMatch)) {
            $awardName = trim($awardMatch[1]);
            $yearsStr = trim($awardMatch[2]);

            // Special case: "Yinka Award: 1988-Eternity"
            if (str_contains($yearsStr, 'Eternity')) {
                $awards[] = ['year' => 1988, 'award' => 'Yinka Award', 'name' => $gmName];
                echo "  [{$gmName}] Yinka Award: 1988 (1988-Eternity)\n";
                continue;
            }

            // Parse comma-separated years
            $yearParts = explode(',', $yearsStr);
            foreach ($yearParts as $yearPart) {
                $yearPart = trim($yearPart);
                if (preg_match('/^\d{4}$/', $yearPart)) {
                    $year = (int) $yearPart;
                    $awards[] = ['year' => $year, 'award' => $awardName, 'name' => $gmName];
                    echo "  [{$gmName}] {$awardName}: {$year}\n";
                } else {
                    $gaps[] = "prim={$row['prim']}: Unparseable year '{$yearPart}' in '{$line}'";
                }
            }
            continue;
        }

        // Pattern 2: "YYYY Olympic/Olmypic ... Medalist (Country)" (e.g., "2003 Olympic Bronze Medalist (USA)")
        // Handles typos like "Olmypic" by matching any characters between Ol and pic
        if (preg_match('/^(\d{4})\s+Ol\w+pic\s+(.+)$/', $line, $olympicMatch)) {
            $year = (int) $olympicMatch[1];
            // Normalize "Olmypic" typo to "Olympic"
            $awardName = "Olympic " . trim($olympicMatch[2]);
            $awards[] = ['year' => $year, 'award' => $awardName, 'name' => $gmName];
            echo "  [{$gmName}] {$awardName}: {$year}\n";
            continue;
        }

        // If we get here, the line didn't match any known pattern
        $gaps[] = "prim={$row['prim']}: Unrecognized award line: '{$line}'";
    }
}

// ---------------------------------------------------------------------------
// 4. Report results
// ---------------------------------------------------------------------------

echo "\n" . str_repeat('=', 60) . "\n";
echo "Parsed " . count($awards) . " individual GM award entries.\n";

if ($gaps !== []) {
    echo "\n--- GAPS / WARNINGS ---\n";
    foreach ($gaps as $gap) {
        echo "  WARNING: {$gap}\n";
    }
    echo "\n";
}

// ---------------------------------------------------------------------------
// 5. Insert into ibl_gm_awards
// ---------------------------------------------------------------------------

if ($awards === []) {
    echo "No awards to insert.\n";
    exit(0);
}

// Sort by year, then award name, then GM name for clean ordering
usort($awards, function (array $a, array $b): int {
    return $a['year'] <=> $b['year']
        ?: strcmp($a['award'], $b['award'])
        ?: strcmp($a['name'], $b['name']);
});

if ($dryRun) {
    echo "\n--- DRY RUN: Would insert these rows ---\n";
    echo sprintf("%-6s %-35s %s\n", "Year", "Award", "Name");
    echo str_repeat('-', 80) . "\n";
    foreach ($awards as $entry) {
        echo sprintf("%-6d %-35s %s\n", $entry['year'], $entry['award'], $entry['name']);
    }
    echo "\nTotal: " . count($awards) . " rows would be inserted.\n";
} else {
    $stmt = $mysqli_db->prepare("INSERT INTO ibl_gm_awards (year, Award, name) VALUES (?, ?, ?)");
    if ($stmt === false) {
        die("Failed to prepare INSERT statement: " . $mysqli_db->error . "\n");
    }

    $inserted = 0;
    foreach ($awards as $entry) {
        $stmt->bind_param('iss', $entry['year'], $entry['award'], $entry['name']);
        if (!$stmt->execute()) {
            echo "  ERROR inserting ({$entry['year']}, {$entry['award']}, {$entry['name']}): {$stmt->error}\n";
        } else {
            $inserted++;
        }
    }
    $stmt->close();

    echo "\nInserted {$inserted} rows into ibl_gm_awards.\n";

    // Verify
    $countResult = $mysqli_db->query("SELECT COUNT(*) AS cnt FROM ibl_gm_awards");
    if ($countResult !== false) {
        $countRow = $countResult->fetch_assoc();
        echo "Total rows in ibl_gm_awards: {$countRow['cnt']}\n";
        $countResult->free();
    }
}

echo "\nDone.\n";
