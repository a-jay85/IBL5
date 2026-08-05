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

$rows = $repo->listActiveConversations();
$ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
// ONE batched attachment query for the whole actionable set — never an N+1 per tick. An
// empty $ids short-circuits inside fetchAllInList without touching the DB, so the empty-tick
// zero-cost guard is preserved: nothing actionable → zero attachment queries.
$byReport = $repo->findAttachmentsForReportIds($ids);

$out = array_map(
    static function (array $row) use ($byReport): array {
        $withAtt = $row;
        $withAtt['attachments'] = $byReport[(int) $row['id']] ?? [];
        return $withAtt;
    },
    $rows
);

echo json_encode($out), PHP_EOL;
