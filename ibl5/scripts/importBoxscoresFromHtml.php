<?php

/**
 * One-time import script: Import Dec 11-13 box scores from HTML + backfill Dec 7-10 supplementary data
 *
 * Dec 7-10 box scores were imported from IBL5.bxs but are missing quarter scores,
 * attendance, capacity, and W-L records. This script fetches the HTML box score pages
 * from iblhoops.net and:
 *   - Dec 7-10: UPDATEs existing rows with quarter scores, attendance, capacity, W-L
 *   - Dec 11-13: INSERTs full player + team box score rows
 *
 * Usage: php importBoxscoresFromHtml.php [--dry-run]
 */

declare(strict_types=1);

require __DIR__ . '/../mainfile.php';

global $mysqli_db;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$repository = new Boxscore\BoxscoreRepository($mysqli_db);

// --- Configuration ---

$BASE_URL = 'https://iblhoops.net/ibl5/ibl/IBL/box';

$teamNameToId = [
    'Celtics' => 1, 'Heat' => 2, 'Knicks' => 3, 'Nets' => 4, 'Magic' => 5,
    'Bucks' => 6, 'Bulls' => 7, 'Pelicans' => 8, 'Hawks' => 9, 'Sting' => 10,
    'Pacers' => 11, 'Raptors' => 12, 'Jazz' => 13, 'Timberwolves' => 14, 'Nuggets' => 15,
    'Aces' => 16, 'Rockets' => 17, 'Trailblazers' => 18, 'Clippers' => 19,
    'Grizzlies' => 20, 'Lakers' => 21, 'Braves' => 22, 'Suns' => 23, 'Warriors' => 24,
    'Pistons' => 25, 'Kings' => 26, 'Bullets' => 27, 'Mavericks' => 28,
];

$idToTeamName = array_flip($teamNameToId);

// Dates that need UPDATE (already have .bxs data) vs INSERT (no data)
$updateDates = ['2006-12-07', '2006-12-08', '2006-12-09', '2006-12-10'];
$insertDates = ['2006-12-11', '2006-12-12', '2006-12-13'];

// --- Helper Functions ---

/**
 * Fetch and parse an HTML box score page.
 *
 * @return array{
 *   visitorPlayers: list<array{name: string, position: string, playerID: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tpm: int, tpa: int, oreb: int, dreb: int, ast: int, stl: int, tov: int, blk: int, pf: int}>,
 *   homePlayers: list<array{name: string, position: string, playerID: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tpm: int, tpa: int, oreb: int, dreb: int, ast: int, stl: int, tov: int, blk: int, pf: int}>,
 *   visitorTotal: array{name: string, fgm: int, fga: int, ftm: int, fta: int, tpm: int, tpa: int, oreb: int, dreb: int, ast: int, stl: int, tov: int, blk: int, pf: int},
 *   homeTotal: array{name: string, fgm: int, fga: int, ftm: int, fta: int, tpm: int, tpa: int, oreb: int, dreb: int, ast: int, stl: int, tov: int, blk: int, pf: int},
 *   visitorQ1: int, visitorQ2: int, visitorQ3: int, visitorQ4: int, visitorOT: int,
 *   homeQ1: int, homeQ2: int, homeQ3: int, homeQ4: int, homeOT: int,
 *   visitorWins: int, visitorLosses: int, homeWins: int, homeLosses: int,
 *   attendance: int, capacity: int
 * }|null
 */
function parseHtmlBoxscore(string $html): ?array
{
    // Convert from Windows-1252 to UTF-8
    $html = mb_convert_encoding($html, 'UTF-8', 'Windows-1252');

    // Extract all <tr> rows
    if (preg_match_all('/<tr>(.*?)<\/tr>/si', $html, $rowMatches) === 0) {
        return null;
    }

    $rows = $rowMatches[1];

    $visitorPlayers = [];
    $homePlayers = [];
    $visitorTotal = null;
    $homeTotal = null;
    $quarterRows = [];
    $attendance = 0;
    $capacity = 0;

    $seenSecondHeader = false; // Second header row separates visitor from home
    $seenVisitorTotal = false;

    foreach ($rows as $row) {
        // Skip header rows (contain <th> tags)
        if (str_contains($row, '<th>')) {
            if ($seenVisitorTotal) {
                $seenSecondHeader = true;
            }
            continue;
        }

        // Extract <td> contents
        if (preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $row, $cellMatches) === 0) {
            continue;
        }
        $cells = $cellMatches[1];

        if (count($cells) < 2) {
            continue;
        }

        // Check for ATTENDANCE/CAPACITY rows
        $secondCell = trim(strip_tags($cells[1]));
        if (preg_match('/^ATTENDANCE:\s*(\d+)/', $secondCell, $attMatch) === 1) {
            $attendance = (int) $attMatch[1];
            continue;
        }
        if (preg_match('/^CAPACITY:\s*(\d+)/', $secondCell, $capMatch) === 1) {
            $capacity = (int) $capMatch[1];
            continue;
        }

        // Check for quarter score rows: team name with (W - L) pattern
        if (preg_match('/^(.+?)\s*\((\d+)\s*-\s*(\d+)\)/', $secondCell, $qMatch) === 1) {
            $quarterRows[] = [
                'teamName' => trim($qMatch[1]),
                'wins' => (int) $qMatch[2],
                'losses' => (int) $qMatch[3],
                'q1' => (int) trim(strip_tags($cells[2])),
                'q2' => (int) trim(strip_tags($cells[3])),
                'q3' => (int) trim(strip_tags($cells[4])),
                'q4' => (int) trim(strip_tags($cells[5])),
                'ot' => (int) trim(strip_tags($cells[6])),
            ];
            continue;
        }

        // Check if this is a player row (has <a href> link) or team total row
        $hasLink = str_contains($cells[1], '<a ');
        $position = trim(strip_tags($cells[0]));

        if ($hasLink) {
            // Player row - extract playerID from href
            $playerID = 0;
            if (preg_match('/href="[^"]*?(\d+)\.htm"/', $cells[1], $pidMatch) === 1) {
                $playerID = (int) $pidMatch[1];
            }

            $playerName = trim(strip_tags($cells[1]));
            // Truncate to 16 chars to match DB column
            $playerName = mb_substr($playerName, 0, 16);

            // HTML shows total FGM/FGA; DB stores 2-point only (game2GM = fgm - 3pm)
            $totalFgm = (int) trim(strip_tags($cells[3]));
            $totalFga = (int) trim(strip_tags($cells[4]));
            $tpm = (int) trim(strip_tags($cells[7]));
            $tpa = (int) trim(strip_tags($cells[8]));
            $oreb = (int) trim(strip_tags($cells[9]));
            // HTML "reb" column is total rebounds; DB stores DRB = total - ORB
            $totalReb = (int) trim(strip_tags($cells[10]));

            $player = [
                'name' => $playerName,
                'position' => $position,
                'playerID' => $playerID,
                'minutes' => (int) trim(strip_tags($cells[2])),
                'fgm' => $totalFgm - $tpm,
                'fga' => $totalFga - $tpa,
                'ftm' => (int) trim(strip_tags($cells[5])),
                'fta' => (int) trim(strip_tags($cells[6])),
                'tpm' => $tpm,
                'tpa' => $tpa,
                'oreb' => $oreb,
                'dreb' => $totalReb - $oreb,
                'ast' => (int) trim(strip_tags($cells[11])),
                'stl' => (int) trim(strip_tags($cells[12])),
                'tov' => (int) trim(strip_tags($cells[13])),
                'blk' => (int) trim(strip_tags($cells[14])),
                'pf' => (int) trim(strip_tags($cells[15])),
            ];

            if (!$seenVisitorTotal) {
                $visitorPlayers[] = $player;
            } else {
                $homePlayers[] = $player;
            }
        } elseif ($position === '' && !$hasLink && count($cells) >= 16) {
            // Team total row
            $teamName = trim(strip_tags($cells[1]));

            // HTML shows total FGM/FGA; DB stores 2-point only (game2GM = fgm - 3pm)
            $totalFgm = (int) trim(strip_tags($cells[3]));
            $totalFga = (int) trim(strip_tags($cells[4]));
            $tpm = (int) trim(strip_tags($cells[7]));
            $tpa = (int) trim(strip_tags($cells[8]));
            $oreb = (int) trim(strip_tags($cells[9]));
            // HTML "reb" column is total rebounds; DB stores DRB = total - ORB
            $totalReb = (int) trim(strip_tags($cells[10]));

            $total = [
                'name' => $teamName,
                'fgm' => $totalFgm - $tpm,
                'fga' => $totalFga - $tpa,
                'ftm' => (int) trim(strip_tags($cells[5])),
                'fta' => (int) trim(strip_tags($cells[6])),
                'tpm' => $tpm,
                'tpa' => $tpa,
                'oreb' => $oreb,
                'dreb' => $totalReb - $oreb,
                'ast' => (int) trim(strip_tags($cells[11])),
                'stl' => (int) trim(strip_tags($cells[12])),
                'tov' => (int) trim(strip_tags($cells[13])),
                'blk' => (int) trim(strip_tags($cells[14])),
                'pf' => (int) trim(strip_tags($cells[15])),
            ];

            if (!$seenVisitorTotal) {
                $visitorTotal = $total;
                $seenVisitorTotal = true;
            } else {
                $homeTotal = $total;
            }
        }
    }

    if ($visitorTotal === null || $homeTotal === null || count($quarterRows) < 2) {
        return null;
    }

    return [
        'visitorPlayers' => $visitorPlayers,
        'homePlayers' => $homePlayers,
        'visitorTotal' => $visitorTotal,
        'homeTotal' => $homeTotal,
        'visitorQ1' => $quarterRows[0]['q1'],
        'visitorQ2' => $quarterRows[0]['q2'],
        'visitorQ3' => $quarterRows[0]['q3'],
        'visitorQ4' => $quarterRows[0]['q4'],
        'visitorOT' => $quarterRows[0]['ot'],
        'homeQ1' => $quarterRows[1]['q1'],
        'homeQ2' => $quarterRows[1]['q2'],
        'homeQ3' => $quarterRows[1]['q3'],
        'homeQ4' => $quarterRows[1]['q4'],
        'homeOT' => $quarterRows[1]['ot'],
        'visitorWins' => $quarterRows[0]['wins'],
        'visitorLosses' => $quarterRows[0]['losses'],
        'homeWins' => $quarterRows[1]['wins'],
        'homeLosses' => $quarterRows[1]['losses'],
        'attendance' => $attendance,
        'capacity' => $capacity,
    ];
}

// --- Main ---

echo $dryRun ? "=== DRY RUN MODE ===\n\n" : "=== IMPORTING BOX SCORES FROM HTML ===\n\n";

// Step 1: Load schedule data for Dec 7-13
$scheduleQuery = "SELECT SchedID, BoxID, Date, Visitor, Home, VScore, HScore
                   FROM ibl_schedule
                   WHERE Date BETWEEN '2006-12-07' AND '2006-12-13'
                   ORDER BY Date, SchedID";

$stmt = $mysqli_db->prepare($scheduleQuery);
if ($stmt === false) {
    echo "ERROR: Failed to prepare schedule query\n";
    exit(1);
}
$stmt->execute();
$result = $stmt->get_result();

/** @var list<array{SchedID: int, BoxID: int, Date: string, Visitor: int, Home: int, VScore: int, HScore: int}> $scheduleRows */
$scheduleRows = [];
while ($row = $result->fetch_assoc()) {
    /** @var array{SchedID: int, BoxID: int, Date: string, Visitor: int, Home: int, VScore: int, HScore: int} $row */
    $scheduleRows[] = $row;
}
$stmt->close();

// Build gameOfThatDay index: count games within each date
/** @var array<string, int> $dateGameCounter */
$dateGameCounter = [];

/** @var list<array{SchedID: int, BoxID: int, Date: string, Visitor: int, Home: int, VScore: int, HScore: int, gameOfThatDay: int}> $games */
$games = [];
foreach ($scheduleRows as $row) {
    $date = $row['Date'];
    if (!isset($dateGameCounter[$date])) {
        $dateGameCounter[$date] = 0;
    }
    $dateGameCounter[$date]++;

    $games[] = array_merge($row, ['gameOfThatDay' => $dateGameCounter[$date]]);
}

echo "Found " . count($games) . " games in schedule (Dec 7-13)\n\n";

// Step 2: Process each game
$gamesUpdated = 0;
$gamesInserted = 0;
$gamesSkipped = 0;
$playersInserted = 0;
$errors = [];

// Track W-L for validation
/** @var array<int, list<array{date: string, wins: int, losses: int, gameResult: string}>> $teamWLHistory */
$teamWLHistory = [];

foreach ($games as $game) {
    $boxId = $game['BoxID'];
    $date = $game['Date'];
    $visitorTid = $game['Visitor'];
    $homeTid = $game['Home'];
    $gameOfDay = $game['gameOfThatDay'];
    $visitorName = $idToTeamName[$visitorTid] ?? "TID{$visitorTid}";
    $homeName = $idToTeamName[$homeTid] ?? "TID{$homeTid}";

    $isUpdate = in_array($date, $updateDates, true);
    $isInsert = in_array($date, $insertDates, true);

    $url = $BASE_URL . $boxId . '.htm';
    echo "Fetching box{$boxId}.htm ({$date} {$visitorName} @ {$homeName})... ";

    // Fetch HTML
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'user_agent' => 'IBL5-BoxscoreImporter/1.0',
        ],
    ]);
    $html = @file_get_contents($url, false, $context);
    if ($html === false) {
        $errors[] = "Failed to fetch box{$boxId}.htm";
        echo "FAILED\n";
        continue;
    }

    // Parse HTML
    $parsed = parseHtmlBoxscore($html);
    if ($parsed === null) {
        $errors[] = "Failed to parse box{$boxId}.htm";
        echo "PARSE ERROR\n";
        continue;
    }

    echo "OK\n";

    // Track W-L history for validation
    $visitorWon = $game['VScore'] > $game['HScore'];
    $teamWLHistory[$visitorTid][] = [
        'date' => $date,
        'wins' => $parsed['visitorWins'],
        'losses' => $parsed['visitorLosses'],
        'gameResult' => $visitorWon ? 'W' : 'L',
    ];
    $teamWLHistory[$homeTid][] = [
        'date' => $date,
        'wins' => $parsed['homeWins'],
        'losses' => $parsed['homeLosses'],
        'gameResult' => $visitorWon ? 'L' : 'W',
    ];

    if ($isUpdate) {
        // UPDATE existing rows with supplementary data
        if ($dryRun) {
            echo "  WOULD UPDATE team rows: Q scores, attendance={$parsed['attendance']}, "
                . "capacity={$parsed['capacity']}, "
                . "visWL={$parsed['visitorWins']}-{$parsed['visitorLosses']}, "
                . "homeWL={$parsed['homeWins']}-{$parsed['homeLosses']}\n";
            echo "  WOULD UPDATE player rows with W-L, attendance, capacity\n";
            $gamesUpdated++;
            continue;
        }

        // Update ibl_box_scores_teams rows
        $teamUpdateSql = "UPDATE ibl_box_scores_teams SET
            visitorQ1points = ?, visitorQ2points = ?, visitorQ3points = ?, visitorQ4points = ?,
            visitorOTpoints = ?,
            homeQ1points = ?, homeQ2points = ?, homeQ3points = ?, homeQ4points = ?,
            homeOTpoints = ?,
            attendance = ?, capacity = ?,
            visitorWins = ?, visitorLosses = ?,
            homeWins = ?, homeLosses = ?
            WHERE Date = ? AND visitorTeamID = ? AND homeTeamID = ? AND gameOfThatDay = ?";

        $teamStmt = $mysqli_db->prepare($teamUpdateSql);
        if ($teamStmt === false) {
            $errors[] = "Failed to prepare team UPDATE for box{$boxId}";
            continue;
        }
        $teamStmt->bind_param(
            'iiiiiiiiiiiiiiiisiii',
            $parsed['visitorQ1'],
            $parsed['visitorQ2'],
            $parsed['visitorQ3'],
            $parsed['visitorQ4'],
            $parsed['visitorOT'],
            $parsed['homeQ1'],
            $parsed['homeQ2'],
            $parsed['homeQ3'],
            $parsed['homeQ4'],
            $parsed['homeOT'],
            $parsed['attendance'],
            $parsed['capacity'],
            $parsed['visitorWins'],
            $parsed['visitorLosses'],
            $parsed['homeWins'],
            $parsed['homeLosses'],
            $date,
            $visitorTid,
            $homeTid,
            $gameOfDay,
        );
        $teamStmt->execute();
        $teamRowsAffected = $teamStmt->affected_rows;
        $teamStmt->close();

        // Update ibl_box_scores player rows
        $playerUpdateSql = "UPDATE ibl_box_scores SET
            attendance = ?, capacity = ?,
            visitorWins = ?, visitorLosses = ?,
            homeWins = ?, homeLosses = ?
            WHERE Date = ? AND visitorTID = ? AND homeTID = ? AND gameOfThatDay = ?";

        $playerStmt = $mysqli_db->prepare($playerUpdateSql);
        if ($playerStmt === false) {
            $errors[] = "Failed to prepare player UPDATE for box{$boxId}";
            continue;
        }
        $playerStmt->bind_param(
            'iiiiiisiii',
            $parsed['attendance'],
            $parsed['capacity'],
            $parsed['visitorWins'],
            $parsed['visitorLosses'],
            $parsed['homeWins'],
            $parsed['homeLosses'],
            $date,
            $visitorTid,
            $homeTid,
            $gameOfDay,
        );
        $playerStmt->execute();
        $playerRowsAffected = $playerStmt->affected_rows;
        $playerStmt->close();

        echo "  Updated {$teamRowsAffected} team rows, {$playerRowsAffected} player rows\n";
        $gamesUpdated++;
    } elseif ($isInsert) {
        // Check for existing data first
        $existing = $repository->findTeamBoxscore($date, $visitorTid, $homeTid, $gameOfDay);
        if ($existing !== null) {
            echo "  SKIP: already exists\n";
            $gamesSkipped++;
            continue;
        }

        if ($dryRun) {
            echo "  WOULD INSERT: "
                . count($parsed['visitorPlayers']) . " visitor + "
                . count($parsed['homePlayers']) . " home players + 2 team totals\n";
            $gamesInserted++;
            continue;
        }

        // Insert visitor team total
        $repository->insertTeamBoxscore(
            $date,
            $parsed['visitorTotal']['name'],
            $gameOfDay,
            $visitorTid,
            $homeTid,
            $parsed['attendance'],
            $parsed['capacity'],
            $parsed['visitorWins'],
            $parsed['visitorLosses'],
            $parsed['homeWins'],
            $parsed['homeLosses'],
            $parsed['visitorQ1'],
            $parsed['visitorQ2'],
            $parsed['visitorQ3'],
            $parsed['visitorQ4'],
            $parsed['visitorOT'],
            $parsed['homeQ1'],
            $parsed['homeQ2'],
            $parsed['homeQ3'],
            $parsed['homeQ4'],
            $parsed['homeOT'],
            $parsed['visitorTotal']['fgm'],
            $parsed['visitorTotal']['fga'],
            $parsed['visitorTotal']['ftm'],
            $parsed['visitorTotal']['fta'],
            $parsed['visitorTotal']['tpm'],
            $parsed['visitorTotal']['tpa'],
            $parsed['visitorTotal']['oreb'],
            $parsed['visitorTotal']['dreb'],
            $parsed['visitorTotal']['ast'],
            $parsed['visitorTotal']['stl'],
            $parsed['visitorTotal']['tov'],
            $parsed['visitorTotal']['blk'],
            $parsed['visitorTotal']['pf'],
        );

        // Insert home team total
        $repository->insertTeamBoxscore(
            $date,
            $parsed['homeTotal']['name'],
            $gameOfDay,
            $visitorTid,
            $homeTid,
            $parsed['attendance'],
            $parsed['capacity'],
            $parsed['visitorWins'],
            $parsed['visitorLosses'],
            $parsed['homeWins'],
            $parsed['homeLosses'],
            $parsed['visitorQ1'],
            $parsed['visitorQ2'],
            $parsed['visitorQ3'],
            $parsed['visitorQ4'],
            $parsed['visitorOT'],
            $parsed['homeQ1'],
            $parsed['homeQ2'],
            $parsed['homeQ3'],
            $parsed['homeQ4'],
            $parsed['homeOT'],
            $parsed['homeTotal']['fgm'],
            $parsed['homeTotal']['fga'],
            $parsed['homeTotal']['ftm'],
            $parsed['homeTotal']['fta'],
            $parsed['homeTotal']['tpm'],
            $parsed['homeTotal']['tpa'],
            $parsed['homeTotal']['oreb'],
            $parsed['homeTotal']['dreb'],
            $parsed['homeTotal']['ast'],
            $parsed['homeTotal']['stl'],
            $parsed['homeTotal']['tov'],
            $parsed['homeTotal']['blk'],
            $parsed['homeTotal']['pf'],
        );

        $gamePlayerCount = 0;

        // Insert visitor players
        foreach ($parsed['visitorPlayers'] as $player) {
            $uuid = Utilities\UuidGenerator::generateUuid();
            $repository->insertPlayerBoxscore(
                $date,
                $uuid,
                $player['name'],
                $player['position'],
                $player['playerID'],
                $visitorTid,
                $homeTid,
                $gameOfDay,
                $parsed['attendance'],
                $parsed['capacity'],
                $parsed['visitorWins'],
                $parsed['visitorLosses'],
                $parsed['homeWins'],
                $parsed['homeLosses'],
                $visitorTid,
                $player['minutes'],
                $player['fgm'],
                $player['fga'],
                $player['ftm'],
                $player['fta'],
                $player['tpm'],
                $player['tpa'],
                $player['oreb'],
                $player['dreb'],
                $player['ast'],
                $player['stl'],
                $player['tov'],
                $player['blk'],
                $player['pf'],
            );
            $gamePlayerCount++;
        }

        // Insert home players
        foreach ($parsed['homePlayers'] as $player) {
            $uuid = Utilities\UuidGenerator::generateUuid();
            $repository->insertPlayerBoxscore(
                $date,
                $uuid,
                $player['name'],
                $player['position'],
                $player['playerID'],
                $visitorTid,
                $homeTid,
                $gameOfDay,
                $parsed['attendance'],
                $parsed['capacity'],
                $parsed['visitorWins'],
                $parsed['visitorLosses'],
                $parsed['homeWins'],
                $parsed['homeLosses'],
                $homeTid,
                $player['minutes'],
                $player['fgm'],
                $player['fga'],
                $player['ftm'],
                $player['fta'],
                $player['tpm'],
                $player['tpa'],
                $player['oreb'],
                $player['dreb'],
                $player['ast'],
                $player['stl'],
                $player['tov'],
                $player['blk'],
                $player['pf'],
            );
            $gamePlayerCount++;
        }

        $playersInserted += $gamePlayerCount;
        echo "  Inserted 2 team rows + {$gamePlayerCount} player rows\n";
        $gamesInserted++;
    }
}

// --- W-L Consistency Validation ---

echo "\n=== W-L CONSISTENCY CHECK ===\n";

$wlWarnings = 0;
foreach ($teamWLHistory as $tid => $history) {
    $teamName = $idToTeamName[$tid] ?? "TID{$tid}";

    // Sort by date (already in order from schedule, but be safe)
    usort($history, static fn(array $a, array $b): int => strcmp($a['date'], $b['date']));

    for ($i = 1; $i < count($history); $i++) {
        $prev = $history[$i - 1];
        $curr = $history[$i];

        // The W-L in the box score is the record BEFORE the game.
        // After prev game: prevWins + (prevResult=W ? 1 : 0), prevLosses + (prevResult=L ? 1 : 0)
        $expectedWins = $prev['wins'] + ($prev['gameResult'] === 'W' ? 1 : 0);
        $expectedLosses = $prev['losses'] + ($prev['gameResult'] === 'L' ? 1 : 0);

        if ($curr['wins'] !== $expectedWins || $curr['losses'] !== $expectedLosses) {
            echo "  WARNING: {$teamName} - after {$prev['date']} ({$prev['wins']}-{$prev['losses']} + {$prev['gameResult']})"
                . " expected {$expectedWins}-{$expectedLosses}"
                . " but {$curr['date']} shows {$curr['wins']}-{$curr['losses']}\n";
            $wlWarnings++;
        }
    }
}

if ($wlWarnings === 0) {
    echo "  All team W-L progressions are consistent.\n";
} else {
    echo "  {$wlWarnings} warning(s) found.\n";
}

// --- Summary ---

echo "\n=== SUMMARY ===\n";
echo "Games updated (Dec 7-10): {$gamesUpdated}\n";
echo "Games inserted (Dec 11-13): {$gamesInserted}\n";
echo "Games skipped: {$gamesSkipped}\n";
echo "Player rows inserted: {$playersInserted}\n";

if ($errors !== []) {
    echo "\nErrors:\n";
    foreach ($errors as $err) {
        echo "  - {$err}\n";
    }
}

if ($dryRun) {
    echo "\n(Dry run - no changes made)\n";
}
