<?php

declare(strict_types=1);

/**
 * claim-next.php — atomically claim the next huntable row into `hunting`.
 *
 * ★ DEADLOCK CONSTRAINT (PR #5a): this script is BUILT + unit-tested but the #5a
 * driver NEVER shells out to it. #5a ships no hunter; a row claimed into `hunting`
 * with no hunter behind it would hold the single-flight lease slot forever. The
 * claim wiring goes live with the hunter in PR #5b.
 *
 * Usage: php claim-next.php [--owner=<lease-token>]
 *   Prints the claimed row as JSON (ids as strings) on stdout, or nothing on an
 *   empty tick / lost race (exit 0 either way — contention is success).
 */

require __DIR__ . '/_bootstrap.php';

use BugPipeline\BugReportRepository;

/** Lease TTL — how long a claim is held before reclaimStaleLease may steal it. */
const LEASE_TTL_MINUTES = 10;

$args = array_slice($argv, 1);
$owner = gethostname() . ':' . getmypid();
foreach ($args as $arg) {
    if (is_string($arg) && str_starts_with($arg, '--owner=')) {
        $owner = substr($arg, strlen('--owner='));
    }
}
if ($owner === '' || $owner === false) {
    $owner = 'unknown:' . getmypid();
}

$leaseExpires = date('Y-m-d H:i:s', time() + LEASE_TTL_MINUTES * 60);

$repo = new BugReportRepository($mysqli_db);

// First take over any crashed hunt whose lease expired; else claim the oldest queued row.
$row = $repo->reclaimStaleLease($owner, $leaseExpires);
if ($row === null) {
    $row = $repo->claimNextQueued($owner, $leaseExpires);
}

// Empty tick / lost race: print nothing, exit 0 (mirrors runEngineShadow's lock-contention convention).
if ($row === null) {
    exit(0);
}

echo json_encode($row), PHP_EOL;
