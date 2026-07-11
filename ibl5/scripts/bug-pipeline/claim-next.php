<?php

declare(strict_types=1);

/**
 * claim-next.php — atomically claim a row into `hunting` for the PR #5b hunter.
 *
 * Two modes, both single-flight-safe and both printing the claimed row as JSON
 * (ids as strings) on stdout, or nothing on an empty tick / lost race (exit 0
 * either way — contention is success, mirroring runEngineShadow's lock convention):
 *
 *   (default)        Reclaim a crashed hunt (expired lease) if one exists, else claim
 *                    the oldest READY-TO-HUNT queued row (classified + not usage-parked).
 *   --resume=<id>    Resume a specific usage-limit-parked hunt: `blocked` → `hunting`.
 *                    The atomic `WHERE status='blocked'` guard makes overlapping ticks safe.
 *
 * Usage: php claim-next.php [--owner=<lease-token>] [--resume=<id>]
 */

require __DIR__ . '/_bootstrap.php';

use BugPipeline\BugReportRepository;

/** Lease TTL — how long a claim is held before reclaimStaleLease may steal it. */
const LEASE_TTL_MINUTES = 10;

$args = array_slice($argv, 1);
$owner = gethostname() . ':' . getmypid();
$resumeId = null;
foreach ($args as $arg) {
    if (!is_string($arg)) {
        continue;
    }
    if (str_starts_with($arg, '--owner=')) {
        $owner = substr($arg, strlen('--owner='));
    } elseif (str_starts_with($arg, '--resume=')) {
        $resumeId = substr($arg, strlen('--resume='));
    }
}
if ($owner === '' || $owner === false) {
    $owner = 'unknown:' . getmypid();
}

$leaseExpires = date('Y-m-d H:i:s', time() + LEASE_TTL_MINUTES * 60);

$repo = new BugReportRepository($mysqli_db);

if ($resumeId !== null) {
    if (!ctype_digit($resumeId)) {
        fwrite(STDERR, "claim-next: --resume must be a positive integer.\n");
        exit(1);
    }
    $id = (int) $resumeId;
    // Lost-race safe: only the tick whose UPDATE still sees status='blocked' flips the row.
    $row = $repo->resumeBlockedHunt($owner, $leaseExpires, $id) ? $repo->findById($id) : null;
} else {
    // First take over any crashed hunt whose lease expired; else claim the oldest huntable row.
    $row = $repo->reclaimStaleLease($owner, $leaseExpires);
    if ($row === null) {
        $row = $repo->claimNextHuntable($owner, $leaseExpires);
    }
}

// Empty tick / lost race: print nothing, exit 0.
if ($row === null) {
    exit(0);
}

echo json_encode($row), PHP_EOL;
