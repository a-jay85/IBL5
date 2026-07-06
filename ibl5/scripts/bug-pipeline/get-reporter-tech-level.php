<?php

declare(strict_types=1);

/**
 * get-reporter-tech-level.php — read a reporter's tech level.
 *
 * Usage: php get-reporter-tech-level.php <discord_id>
 *   <discord_id> is a snowflake carried as a STRING (never (int)-cast). The repo
 *   binds it "s" against BIGINT UNSIGNED — a metacharacter-laden argv value is a
 *   literal profile key, never interpolated into SQL.
 *
 * Prints {"discord_id":"<id>","tech_level":"technical"|"nontechnical"|null}.
 */

require __DIR__ . '/_bootstrap.php';

use BugPipeline\BugReportRepository;

$discordId = $argv[1] ?? null;
if (!is_string($discordId) || $discordId === '') {
    fwrite(STDERR, "get-reporter-tech-level: <discord_id> is required.\n");
    exit(1);
}

$repo = new BugReportRepository($mysqli_db);
$level = $repo->getReporterTechLevel($discordId);

echo json_encode(['discord_id' => $discordId, 'tech_level' => $level]), PHP_EOL;
