<?php

declare(strict_types=1);

/**
 * list-pr-open.php — enumerate rows awaiting PR reconciliation (PR #5b Phase 5 Fork B).
 *
 * Prints a JSON array of every `pr_open` row (ids as strings) for the trusted cron's async
 * reconcile pass: fill `pr_number` from `gh pr list`, and on merge advance pr_open → fixed.
 * Always a JSON array — `[]` when nothing is pending. Read-only; claims no lease.
 *
 * Usage: php list-pr-open.php
 */

require __DIR__ . '/_bootstrap.php';

use BugPipeline\BugReportRepository;

$repo = new BugReportRepository($mysqli_db);

echo json_encode($repo->listPrOpen()), PHP_EOL;
