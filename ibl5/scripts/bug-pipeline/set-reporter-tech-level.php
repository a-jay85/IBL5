<?php

declare(strict_types=1);

/**
 * set-reporter-tech-level.php — upsert a reporter's tech level.
 *
 * Usage: php set-reporter-tech-level.php <discord_id> <level>
 *   <discord_id> is a snowflake carried as a STRING (never (int)-cast).
 *   <level> must be exactly one of: technical, nontechnical.
 *
 * Prints {"ok":true} on success. Bad argv → STDERR + exit 1 before any repo call.
 */

require __DIR__ . '/_bootstrap.php';

use BugPipeline\BugReportRepository;

const VALID_LEVELS = ['technical', 'nontechnical'];

$discordId = $argv[1] ?? null;
$level = $argv[2] ?? null;

if (!is_string($discordId) || $discordId === '') {
    fwrite(STDERR, "set-reporter-tech-level: <discord_id> is required.\n");
    exit(1);
}
if (!is_string($level) || !in_array($level, VALID_LEVELS, true)) {
    fwrite(STDERR, 'set-reporter-tech-level: <level> must be one of: ' . implode(', ', VALID_LEVELS) . ".\n");
    exit(1);
}

$repo = new BugReportRepository($mysqli_db);
$repo->upsertReporterProfile($discordId, $level);

echo json_encode(['ok' => true]), PHP_EOL;
