<?php

declare(strict_types=1);

/**
 * transition.php — the state-machine writer wrapper (thin arg-parse over
 * BugReportRepository::transition() plus the three conditional writers).
 *
 * Usage: php transition.php <id> <status> [opts]
 *   Positional: <id> (integer PK), <status> (one of the §3a status ENUM values).
 *   Value opts (all bound parameterized in the repo — zero SQL here):
 *     --class=<bug|feature|not_a_thing>   --pr=<int>   --issue=<int>
 *     --thread-id=<snowflake-string>      --approval-message-id=<snowflake-string>
 *     --blocked-until="YYYY-MM-DD HH:MM:SS"   --attempts=<int>
 *   Flag opts:
 *     --release-lease        NULL lease_owner + lease_expires in the same UPDATE
 *     --reminder-now         stamp reminder_sent_at (at-most-once: WHERE reminder_sent_at IS NULL)
 *     --last-processed-now   stamp last_processed_at = NOW()
 *     --clear-blocked        blocked_until = NULL (usage-limit park resume)
 *
 * Snowflake ids (thread_id, approval_message_id) are carried as STRINGS end-to-end —
 * never (int)-cast (a 19-digit snowflake overflows / rounds and corrupts downstream).
 *
 * On success: echo {"ok":true,"id":<int>,"status":"<status>"} and exit 0.
 * On bad argv: fwrite(STDERR, ...) and exit 1 BEFORE any repo call.
 */

require __DIR__ . '/_bootstrap.php';

use BugPipeline\BugReportRepository;

/** The exact §3a status ENUM allow-list (migration 153). */
const VALID_STATUSES = [
    'queued', 'awaiting_info', 'hunting', 'blocked', 'pr_open', 'fixed',
    'needs_human', 'parked_idle', 'gathering', 'awaiting_ajay', 'planned', 'dropped',
];
const VALID_CLASSES = ['bug', 'feature', 'not_a_thing'];

function fail(string $msg): never
{
    fwrite(STDERR, "transition: {$msg}\n");
    exit(1);
}

// ── Manual argv parse (positional may precede options) ────────────────────────
$positional = [];
$valOpts = [];
$flags = [];
foreach (array_slice($argv, 1) as $arg) {
    if (is_string($arg) && str_starts_with($arg, '--')) {
        $body = substr($arg, 2);
        if (str_contains($body, '=')) {
            [$k, $v] = explode('=', $body, 2);
            $valOpts[$k] = $v;
        } else {
            $flags[$body] = true;
        }
    } else {
        $positional[] = $arg;
    }
}

$idArg = $positional[0] ?? null;
$status = $positional[1] ?? null;

if (!is_string($idArg) || !ctype_digit($idArg)) {
    fail('<id> must be a positive integer.');
}
if (!is_string($status) || !in_array($status, VALID_STATUSES, true)) {
    fail('<status> must be one of: ' . implode(', ', VALID_STATUSES) . '.');
}
$id = (int) $idArg;

// ── Map value opts → repo transition() $opts (parameterized column values) ────
/** @var array<string, int|string> $opts */
$opts = [];

if (isset($valOpts['class'])) {
    if (!in_array($valOpts['class'], VALID_CLASSES, true)) {
        fail('--class must be one of: ' . implode(', ', VALID_CLASSES) . '.');
    }
    $opts['class'] = $valOpts['class'];
}
$intOpt = static function (string $key, string $label) use ($valOpts): ?int {
    if (!isset($valOpts[$key])) {
        return null;
    }
    if (!ctype_digit($valOpts[$key])) {
        fail("--{$key} ({$label}) must be a non-negative integer.");
    }
    return (int) $valOpts[$key];
};
if (($pr = $intOpt('pr', 'pr_number')) !== null) {
    $opts['pr_number'] = $pr;
}
if (($issue = $intOpt('issue', 'issue_number')) !== null) {
    $opts['issue_number'] = $issue;
}
if (($attempts = $intOpt('attempts', 'hunt_attempts')) !== null) {
    $opts['hunt_attempts'] = $attempts;
}
// Snowflakes: pass through as strings, NEVER cast.
if (isset($valOpts['thread-id'])) {
    $opts['thread_id'] = $valOpts['thread-id'];
}
if (isset($valOpts['approval-message-id'])) {
    $opts['approval_message_id'] = $valOpts['approval-message-id'];
}
if (isset($valOpts['blocked-until'])) {
    $opts['blocked_until'] = $valOpts['blocked-until'];
}

$releaseLease = isset($flags['release-lease']);

$repo = new BugReportRepository($mysqli_db);
$repo->transition($id, $status, $opts, $releaseLease);

// ── Conditional writers transition()'s value-bind can't express ───────────────
if (isset($flags['reminder-now'])) {
    $repo->markReminderSent($id);
}
if (isset($flags['last-processed-now'])) {
    $repo->stampLastProcessed($id);
}
if (isset($flags['clear-blocked'])) {
    $repo->clearBlocked($id);
}

echo json_encode(['ok' => true, 'id' => $id, 'status' => $status]), PHP_EOL;
