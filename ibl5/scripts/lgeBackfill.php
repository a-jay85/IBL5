<?php

declare(strict_types=1);

// Backfill script: Scans scoNonFiles/[dir]/IBL5.lge, parses each,
// and upserts via LeagueConfigService.
//
// Usage: php scripts/lgeBackfill.php
//
// Multiple snapshots per season (HEAT + Finals) produce identical team data.
// The upsert handles deduplication via the unique key on (season_ending_year, team_slot).

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

global $mysqli_db;

$lgeRepo = new LeagueConfig\LeagueConfigRepository($mysqli_db);
$lgeService = new LeagueConfig\LeagueConfigService($lgeRepo);

$pattern = $_SERVER['DOCUMENT_ROOT'] . '/ibl5/scoNonFiles/*/IBL5.lge';
$files = glob($pattern);

if ($files === false || $files === []) {
    echo "No .lge files found matching: {$pattern}\n";
    exit(1);
}

sort($files);

echo "Found " . count($files) . " .lge files to process.\n\n";

/** @var array<int, array{season: string, teams: int, files: int}> $seasonSummary */
$seasonSummary = [];
$errors = 0;

foreach ($files as $file) {
    $dirName = basename(dirname($file));
    echo "Processing: {$dirName}/IBL5.lge ... ";

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
