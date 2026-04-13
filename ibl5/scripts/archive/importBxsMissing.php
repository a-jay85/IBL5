<?php

/**
 * One-time import script: Extract Dec 7-10 box scores from IBL5.bxs
 *
 * The .bxs file uses 3000-byte records with 94-byte player entries,
 * which differs from the .sco format (2000/53). This script reads
 * the .bxs format directly and inserts via BoxscoreRepository.
 *
 * Missing from .bxs: quarter scores, attendance, capacity, W-L records.
 * These are set to 0. Total points are auto-computed by the calc_points
 * generated column in ibl_box_scores_teams.
 *
 * Usage: php importBxsMissing.php [--dry-run]
 */

declare(strict_types=1);

require __DIR__ . '/../mainfile.php';

global $mysqli_db;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$repository = new Boxscore\BoxscoreRepository($mysqli_db);

// --- Configuration ---

$bxsPath = $_SERVER['DOCUMENT_ROOT'] . '/ibl5/../IBL5.bxs';
if (!is_file($bxsPath)) {
    // Fallback: try from script directory
    $bxsPath = dirname(__DIR__, 2) . '/IBL5.bxs';
}

$BXS_RECORD_SIZE = 3000;
$BXS_PLAYER_SIZE = 94;
$BXS_PLAYER_START = 95;
$BXS_PLAYER_COUNT = 30;
$SEASON_ENDING_YEAR = 2007;

$teamNameToId = [
    'Celtics' => 1, 'Heat' => 2, 'Knicks' => 3, 'Nets' => 4, 'Magic' => 5,
    'Bucks' => 6, 'Bulls' => 7, 'Pelicans' => 8, 'Hawks' => 9, 'Sting' => 10,
    'Pacers' => 11, 'Raptors' => 12, 'Jazz' => 13, 'Timberwolves' => 14, 'Nuggets' => 15,
    'Aces' => 16, 'Rockets' => 17, 'Trailblazers' => 18, 'Clippers' => 19,
    'Grizzlies' => 20, 'Lakers' => 21, 'Braves' => 22, 'Suns' => 23, 'Warriors' => 24,
    'Pistons' => 25, 'Kings' => 26, 'Bullets' => 27, 'Mavericks' => 28,
];

// Records to import: skip first 5 duplicates for Dec 7, then straight through Dec 8-10
// Dec 7 second set (records 22231-22237): offsets 66693000-66711000
// Dec 8 (records 22238-22244): offsets 66714000-66732000
// Dec 9 (records 22245-22251): offsets 66735000-66753000
// Dec 10 (records 22252-22258): offsets 66756000-66774000
$gameRecordOffsets = [];
for ($i = 0; $i < 28; $i++) {
    $gameRecordOffsets[] = 66693000 + $i * $BXS_RECORD_SIZE;
}

// Schedule-derived gameOfThatDay: games appear in schedule order (1-7 per date)
// The .bxs record order matches schedule order within each date
$scheduleOrder = [
    // Dec 7: 7 games
    '2006-12-07' => [
        '4-22' => 1, '28-23' => 2, '14-27' => 3, '5-7' => 4,
        '21-24' => 5, '19-9' => 6, '11-17' => 7,
    ],
    // Dec 8: 7 games
    '2006-12-08' => [
        '12-7' => 1, '13-20' => 2, '10-4' => 3, '14-26' => 4,
        '1-28' => 5, '17-24' => 6, '21-22' => 7,
    ],
    // Dec 9: 7 games
    '2006-12-09' => [
        '24-23' => 1, '12-6' => 2, '15-9' => 3, '18-19' => 4,
        '16-7' => 5, '25-21' => 6, '11-2' => 7,
    ],
    // Dec 10: 7 games
    '2006-12-10' => [
        '28-27' => 1, '1-26' => 2, '6-14' => 3, '2-15' => 4,
        '11-22' => 5, '20-4' => 6, '12-3' => 7,
    ],
];

// --- Helper functions ---

/**
 * Parse a .bxs game record header (95 bytes)
 *
 * @return array{date: string, visName: string, homeName: string, visScore: int, homeScore: int, visId: int, homeId: int}|null
 */
function parseBxsHeader(string $record, int $seasonEndingYear, array $teamNameToId): ?array
{
    $monthRaw = intval(substr($record, 48, 2));
    $dayRaw = intval(substr($record, 52, 2));
    $month = $monthRaw + 10;
    $day = $dayRaw + 1;

    $year = $seasonEndingYear;
    if ($month > 12) {
        $month -= 12;
    } else {
        $year = $seasonEndingYear - 1;
    }

    $visName = trim(substr($record, 57, 16));
    $homeName = trim(substr($record, 76, 16));

    if ($visName === '' || $homeName === '') {
        return null;
    }

    $visId = $teamNameToId[$visName] ?? null;
    $homeId = $teamNameToId[$homeName] ?? null;

    if ($visId === null || $homeId === null) {
        return null;
    }

    return [
        'date' => sprintf('%d-%02d-%02d', $year, $month, $day),
        'visName' => $visName,
        'homeName' => $homeName,
        'visScore' => intval(substr($record, 73, 3)),
        'homeScore' => intval(substr($record, 92, 3)),
        'visId' => $visId,
        'homeId' => $homeId,
    ];
}

/**
 * Parse a .bxs player entry (94 bytes)
 *
 * @return array{name: string, position: string, playerID: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tpm: int, tpa: int, oreb: int, dreb: int, ast: int, stl: int, tov: int, blk: int, pf: int}
 */
function parseBxsPlayer(string $entry): array
{
    $rawName = substr($entry, 0, 32);
    $name = trim($rawName);
    $playerID = intval(trim(substr($entry, 32, 6)));

    // Extract position from name prefix (PG, SG, SF, PF, C)
    $position = '';
    if ($playerID !== 0 && $name !== '') {
        $firstTwo = substr($rawName, 0, 2);
        if ($firstTwo === 'PG' || $firstTwo === 'SG' || $firstTwo === 'SF' || $firstTwo === 'PF') {
            $position = $firstTwo;
            $name = trim(substr($rawName, 2, 30));
        } elseif (trim(substr($rawName, 0, 2)) === 'C') {
            $position = 'C';
            $name = trim(substr($rawName, 2, 30));
        }
    }

    // Convert name from Windows-1252 to UTF-8 (e.g. 0x9A → š)
    $converted = mb_convert_encoding($name, 'UTF-8', 'Windows-1252');
    if ($converted !== false) {
        $name = $converted;
    }

    // Truncate to 16 chars to match ibl_box_scores.name varchar(16)
    $name = mb_substr($name, 0, 16);

    return [
        'name' => $name,
        'position' => $position,
        'playerID' => $playerID,
        'minutes' => intval(trim(substr($entry, 38, 4))),
        'fgm' => intval(trim(substr($entry, 42, 4))),
        'fga' => intval(trim(substr($entry, 46, 4))),
        'ftm' => intval(trim(substr($entry, 50, 4))),
        'fta' => intval(trim(substr($entry, 54, 4))),
        'tpm' => intval(trim(substr($entry, 58, 4))),
        'tpa' => intval(trim(substr($entry, 62, 4))),
        'oreb' => intval(trim(substr($entry, 66, 4))),
        'dreb' => intval(trim(substr($entry, 70, 4))),
        'ast' => intval(trim(substr($entry, 74, 4))),
        'stl' => intval(trim(substr($entry, 78, 4))),
        'tov' => intval(trim(substr($entry, 82, 4))),
        'blk' => intval(trim(substr($entry, 86, 4))),
        'pf' => intval(trim(substr($entry, 90, 4))),
    ];
}

// --- Main ---

$f = @fopen($bxsPath, 'rb');
if ($f === false) {
    echo "ERROR: Cannot open .bxs file at: {$bxsPath}\n";
    exit(1);
}

echo $dryRun ? "=== DRY RUN MODE ===\n\n" : "=== IMPORTING BOX SCORES ===\n\n";

$gamesInserted = 0;
$gamesSkipped = 0;
$playersInserted = 0;
$errors = [];

foreach ($gameRecordOffsets as $offset) {
    fseek($f, $offset);
    $record = fread($f, $BXS_RECORD_SIZE);

    if ($record === false || strlen($record) < $BXS_RECORD_SIZE) {
        $errors[] = "Failed to read record at offset {$offset}";
        continue;
    }

    $header = parseBxsHeader($record, $SEASON_ENDING_YEAR, $teamNameToId);
    if ($header === null) {
        $errors[] = "Invalid header at offset {$offset}";
        continue;
    }

    // Look up gameOfThatDay from schedule
    $matchupKey = $header['visId'] . '-' . $header['homeId'];
    $gameOfDay = $scheduleOrder[$header['date']][$matchupKey] ?? null;
    if ($gameOfDay === null) {
        $errors[] = "No schedule match for {$header['date']} {$header['visName']} @ {$header['homeName']}";
        continue;
    }

    // Check if game already exists
    $existing = $repository->findTeamBoxscore(
        $header['date'],
        $header['visId'],
        $header['homeId'],
        $gameOfDay
    );
    if ($existing !== null) {
        echo "SKIP: {$header['date']} {$header['visName']} @ {$header['homeName']} (already exists)\n";
        $gamesSkipped++;
        continue;
    }

    echo sprintf(
        "%s: %s %s(%d) %d - %d %s(%d) [game#%d]\n",
        $dryRun ? 'WOULD INSERT' : 'INSERT',
        $header['date'],
        $header['visName'],
        $header['visId'],
        $header['visScore'],
        $header['homeScore'],
        $header['homeName'],
        $header['homeId'],
        $gameOfDay,
    );

    if ($dryRun) {
        $gamesInserted++;
        continue;
    }

    // Process 30 player entries
    $visitorTeamTotalSeen = false;
    $gamePlayers = 0;

    for ($i = 0; $i < $BXS_PLAYER_COUNT; $i++) {
        $entryOffset = $BXS_PLAYER_START + $i * $BXS_PLAYER_SIZE;
        $entry = substr($record, $entryOffset, $BXS_PLAYER_SIZE);
        $player = parseBxsPlayer($entry);

        if ($player['name'] === '') {
            continue;
        }

        if ($player['playerID'] === 0) {
            // Team total row
            $teamName = $player['name'];
            if (!$visitorTeamTotalSeen) {
                $visitorTeamTotalSeen = true;
                // Use the actual team name from the .bxs (should match header)
            }

            $repository->insertTeamBoxscore(
                $header['date'],
                $teamName,
                $gameOfDay,
                $header['visId'],
                $header['homeId'],
                0, // attendance
                0, // capacity
                0, // visitorWins
                0, // visitorLosses
                0, // homeWins
                0, // homeLosses
                0, // visitorQ1points
                0, // visitorQ2points
                0, // visitorQ3points
                0, // visitorQ4points
                0, // visitorOTpoints
                0, // homeQ1points
                0, // homeQ2points
                0, // homeQ3points
                0, // homeQ4points
                0, // homeOTpoints
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
            $gamePlayers++;
        } else {
            // Player row
            $playerUuid = Utilities\UuidGenerator::generateUuid();
            // Determine player's team: first 15 entries are visitor, next 15 are home
            $playerTeamID = $i < 15 ? $header['visId'] : $header['homeId'];

            $repository->insertPlayerBoxscore(
                $header['date'],
                $playerUuid,
                $player['name'],
                $player['position'],
                $player['playerID'],
                $header['visId'],
                $header['homeId'],
                $gameOfDay,
                0, // attendance
                0, // capacity
                0, // visitorWins
                0, // visitorLosses
                0, // homeWins
                0, // homeLosses
                $playerTeamID,
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
            $gamePlayers++;
        }
    }

    $playersInserted += $gamePlayers;
    $gamesInserted++;
    echo "  -> {$gamePlayers} player/team rows inserted\n";
}

fclose($f);

// Sim date update intentionally skipped — this is a backfill of older games.
// Run the normal scoParser.php or manually update sim dates if needed.

echo "\n=== SUMMARY ===\n";
echo "Games inserted: {$gamesInserted}\n";
echo "Games skipped:  {$gamesSkipped}\n";
echo "Player/team rows: {$playersInserted}\n";

if ($errors !== []) {
    echo "\nErrors:\n";
    foreach ($errors as $err) {
        echo "  - {$err}\n";
    }
}

echo "\nNOTE: Dec 11-13 games were NOT found in the .bxs file.\n";
echo "Quarter scores, attendance, capacity, and W-L records are set to 0.\n";
echo "Total points are auto-computed by the calc_points generated column.\n";
