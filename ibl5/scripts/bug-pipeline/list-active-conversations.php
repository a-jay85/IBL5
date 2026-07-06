<?php

declare(strict_types=1);

/**
 * list-active-conversations.php — the tick's actionable-set enumerator.
 *
 * Prints a JSON array of every row the poll-only driver must inspect this tick
 * (see BugReportRepository::listActiveConversations()). Always a JSON array —
 * `[]` when nothing is actionable. This is the wrapper the empty-tick cost guard
 * polls: `[]` → the driver exits cheap having spawned zero `claude` processes.
 *
 * Usage: php list-active-conversations.php
 */

require __DIR__ . '/_bootstrap.php';

use BugPipeline\BugReportRepository;

$repo = new BugReportRepository($mysqli_db);

echo json_encode($repo->listActiveConversations()), PHP_EOL;
