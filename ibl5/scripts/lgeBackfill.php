<?php

declare(strict_types=1);

// Backfill script: Recursively searches a user-specified folder for .lge files,
// parses each, and upserts via LeagueConfigService.
//
// Usage: php scripts/lgeBackfill.php /path/to/folder
//
// Multiple snapshots per season (HEAT + Finals) produce identical team data.
// The upsert handles deduplication via the unique key on (season_ending_year, team_slot).

// Resolve project root from this script's location: scripts/ -> ibl5/
$ibl5Root = dirname(__DIR__);
$_SERVER['DOCUMENT_ROOT'] = dirname($ibl5Root);

require $ibl5Root . '/mainfile.php';

global $mysqli_db;

if (!isset($argv[1]) || $argv[1] === '') {
    echo "Usage: php scripts/lgeBackfill.php /path/to/folder\n";
    echo "\nRecursively searches the folder for .lge files and imports them.\n";
    exit(1);
}

$searchDir = realpath($argv[1]);
if ($searchDir === false || !is_dir($searchDir)) {
    echo "Directory not found: {$argv[1]}\n";
    exit(1);
}

$lgeRepo = new LeagueConfig\LeagueConfigRepository($mysqli_db);
$lgeService = new LeagueConfig\LeagueConfigService($lgeRepo);

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($searchDir, RecursiveDirectoryIterator::SKIP_DOTS),
);

/** @var list<string> $files */
$files = [];
foreach ($iterator as $fileInfo) {
    /** @var SplFileInfo $fileInfo */
    if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'lge') {
        $files[] = $fileInfo->getPathname();
    }
}

if ($files === []) {
    echo "No .lge files found in: {$searchDir}\n";
    exit(1);
}

sort($files);

echo "Searching: {$searchDir}\n";
echo "Found " . count($files) . " .lge file(s) to process.\n\n";

/** @var array<int, array{season: string, teams: int, files: int}> $seasonSummary */
$seasonSummary = [];
$errors = 0;

foreach ($files as $file) {
    // Show path relative to the search directory
    $relativePath = substr($file, strlen($searchDir) + 1);
    echo "Processing: {$relativePath} ... ";

    $result = $lgeService->processLgeFile($file);

    if (!$result['success']) {
        $error = $result['error'] ?? 'Unknown error';
        echo "ERROR: {$error}\n";
        $errors++;
        continue;
    }

    $year = $result['season_ending_year'];
    $teams = $result['teams_stored'];
    $beginningYear = $year - 1;
    $shortEndingYear = substr((string) $year, 2);
    $seasonLabel = $beginningYear . '-' . $shortEndingYear;

    echo "OK ({$seasonLabel}, {$teams} teams)\n";

    if (!isset($seasonSummary[$year])) {
        $seasonSummary[$year] = [
            'season' => $seasonLabel,
            'teams' => $teams,
            'files' => 0,
        ];
    }
    $seasonSummary[$year]['files']++;
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "SUMMARY\n";
echo str_repeat('=', 50) . "\n\n";

ksort($seasonSummary);

printf("%-12s %-8s %-8s\n", 'Season', 'Teams', 'Files');
printf("%-12s %-8s %-8s\n", '------', '-----', '-----');

foreach ($seasonSummary as $summary) {
    printf("%-12s %-8d %-8d\n", $summary['season'], $summary['teams'], $summary['files']);
}

echo "\nSeasons imported: " . count($seasonSummary) . "\n";
echo "Total files processed: " . count($files) . "\n";

if ($errors > 0) {
    echo "Errors: {$errors}\n";
}
